<?php
namespace Blocks;

/**
 *
 */
class UpdatesService extends BaseApplicationComponent
{
	private $_updateModel;
	private $_isSystemOn;

	/**
	 * @param $forceRefresh
	 * @return mixed
	 */
	public function getAllAvailableUpdates($forceRefresh = false)
	{
		$updates = array();

		if (!$forceRefresh && !$this->isUpdateInfoCached())
		{
			return null;
		}

		$updateModel = $this->getUpdateModel($forceRefresh);

		// blocks first.
		if ($updateModel->blocks->versionUpdateStatus == VersionUpdateStatus::UpdateAvailable && count($updateModel->blocks->releases) > 0)
		{
			$notes = $this->_generateUpdateNotes($updateModel->blocks->releases, 'Blocks');

			$updates[] = array(
				'name' => 'Blocks',
				'handle' => 'Blocks',
				'version' => $updateModel->blocks->latestVersion.' Build '.$updateModel->blocks->latestBuild,
				'critical' => $updateModel->blocks->criticalUpdateAvailable,
				'manualUpdateRequired' => $updateModel->blocks->manualUpdateRequired,
				'notes' => $notes,
				'latestVersion' => $updateModel->blocks->latestVersion,
				'latestBuild' => $updateModel->blocks->latestBuild,
			);

		}

		// plugins second.
		if ($updateModel->plugins !== null && count($updateModel->plugins) > 0)
		{
			foreach ($updateModel->plugins as $plugin)
			{
				if ($plugin->status == PluginVersionUpdateStatus::UpdateAvailable && count($plugin->releases) > 0)
				{
					$notes = $this->_generateUpdateNotes($plugin->releases, $plugin->displayName);

					$updates[] = array(
						'name' => $plugin->displayName,
						'handle' => $plugin->class,
						'version' => $plugin->latestVersion,
						'critical' => $plugin->criticalUpdateAvailable,
						'notes' => $notes,
					);
				}
			}
		}

		return $updates;
	}

	/**
	 * @param $blocksReleases
	 * @return bool
	 */
	public function criticalBlocksUpdateAvailable($blocksReleases)
	{
		foreach ($blocksReleases as $blocksRelease)
		{
			if ($blocksRelease->critical)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $blocksReleases
	 * @return bool
	 */
	public function manualUpdateRequired($blocksReleases)
	{
		foreach ($blocksReleases as $blocksRelease)
		{
			if ($blocksRelease->manual_update_required)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $plugins
	 * @return bool
	 */
	public function criticalPluginUpdateAvailable($plugins)
	{
		foreach ($plugins as $plugin)
		{
			if ($plugin->status == PluginVersionUpdateStatus::UpdateAvailable && count($plugin->releases) > 0)
			{
				foreach ($plugin->releases as $release)
				{
					if ($release->critical)
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function isUpdateInfoCached()
	{
		return (isset($this->_updateModel) || blx()->fileCache->get('updateInfo') !== false);
	}

	/**
	 * @return mixed
	 */
	public function isCriticalUpdateAvailable()
	{
		if ((isset($this->_updateModel) && $this->_updateModel->blocks->criticalUpdateAvailable))
		{
			return true;
		}

		return false;
	}

	/**
	 * @return mixed
	 */
	public function isManualUpdateRequired()
	{
		if ((isset($this->_updateModel) && $this->_updateModel->blocks->manualUpdateRequired))
		{
			return true;
		}

		return false;
	}

	/**
	 * @param bool $forceRefresh
	 * @return mixed
	 */
	public function getUpdateModel($forceRefresh = false)
	{
		if (!isset($this->_updateModel) || $forceRefresh)
		{
			$updateModel = false;

			if (!$forceRefresh)
			{
				// get the update info from the cache if it's there
				$updateModel = blx()->fileCache->get('updateInfo');
			}

			// fetch it if it wasn't cached, or if we're forcing a refresh
			if ($forceRefresh || $updateModel === false)
			{
				$etModel = $this->check();

				if ($etModel == null)
				{
					$updateModel = new UpdateModel();
				}
				else
				{
					$updateModel = $etModel->data;
				}

				// cache it and set it to expire according to config
				blx()->fileCache->set('updateinfo', $updateModel);
			}

			$this->_updateModel = $updateModel;
		}

		return $this->_updateModel;
	}

	/**
	 * @return bool
	 */
	public function flushUpdateInfoFromCache()
	{
		Blocks::log('Flushing update info from cache.');

		if (blx()->fileCache->delete('updateInfo'))
		{
			return true;
		}

		return false;
	}

	/**
	 * @param $version
	 * @param $build
	 * @param $releaseDate
	 * @return bool
	 */
	public function setNewBlocksInfo($version, $build, $releaseDate)
	{
		$info = InfoRecord::model()->find();
		$info->version = $version;
		$info->build = $build;
		$info->releaseDate = $releaseDate;

		if ($info->save())
		{
			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function doAppUpdate()
	{
		$appUpdater = new AppUpdater();

		if ($appUpdater->start())
		{
			return true;
		}

		return false;
	}

	/**
	 * @param $pluginHandle
	 * @return bool
	 */
	public function doPluginUpdate($pluginHandle)
	{
		$pluginUpdater = new PluginUpdater($pluginHandle);

		if ($pluginUpdater->start())
		{
			return true;
		}

		return false;
	}

	/**
	 * @return UpdateModel
	 */
	public function check()
	{
		$updateModel = new UpdateModel();
		$updateModel->blocks = new BlocksUpdateModel();
		$updateModel->plugins = array();

		$updateModel->blocks->localBuild = Blocks::getBuild();
		$updateModel->blocks->localVersion = Blocks::getVersion();

		$plugins = blx()->plugins->getEnabledPlugins();

		foreach ($plugins as $plugin)
		{
			$pluginUpdateModel = new PluginUpdateModel();
			$pluginUpdateModel->class = $plugin->getClassHandle();
			$pluginUpdateModel->localVersion = $plugin->version;

			$updateModel->plugins[$plugin->getClassHandle()] = $pluginUpdateModel;
		}

		$etModel = blx()->et->check($updateModel);
		return ($etModel == null ? new EtModel() : $etModel);
	}

	/**
	 * @return bool
	 */
	public function turnSystemOnAfterUpdate()
	{
		// if the system wasn't on before, we're leave it in an off state
		if (!$this->_isSystemOn)
		{
			return true;
		}
		else
		{
			if (Blocks::turnSystemOn())
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @static
	 * @return bool
	 */
	public function turnSystemOffBeforeUpdate()
	{
		// save the current state of the system for possible use later in the request.
		$this->_isSystemOn = Blocks::isSystemOn();

		// if it's not on, don't even bother.
		if ($this->_isSystemOn)
		{
			if (Blocks::turnSystemOff())
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks to see if Blocks can write to a defined set of folders/files that are needed for auto-update to work.
	 *
	 * @return array|null
	 */
	public function getUnwritableFolders()
	{
		$checkPaths = array(
			blx()->path->getAppPath(),
			blx()->path->getPluginsPath(),
		);

		$errorPath = null;

		foreach ($checkPaths as $writablePath)
		{
			if (!IOHelper::isWritable($writablePath))
			{
				$errorPath[] = IOHelper::getRealPath($writablePath);
			}
		}

		return $errorPath;
	}

	/**
	 * @param $updates
	 * @param $name
	 * @return string
	 */
	private function _generateUpdateNotes($updates, $name)
	{
		$notes = '';

		foreach ($updates as $update)
		{
			$notes .= '<h5>'.$name.' '.$update->version.($name == 'Blocks' ? '.'.$update->build : '').'</h5>';
			$notes .= '<ul><li>'.$update->notes.'</li></ul>';
		}

		return $notes;
	}
}
