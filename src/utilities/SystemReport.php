<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\utilities;

use Craft;
use craft\base\PluginInterface;
use craft\base\Utility;
use craft\helpers\App;
use GuzzleHttp\Client;
use RequirementsChecker;
use Twig\Environment;
use Yii;
use yii\base\Module;

/**
 * SystemReport represents a SystemReport dashboard widget.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class SystemReport extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'System Report');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'system-report';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@appicons/check.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $modules = [];
        foreach (Craft::$app->getModules() as $id => $module) {
            if ($module instanceof PluginInterface) {
                continue;
            }
            if ($module instanceof Module) {
                $modules[$id] = get_class($module);
            } else if (is_string($module)) {
                $modules[$id] = $module;
            } else if (is_array($module) && isset($module['class'])) {
                $modules[$id] = $module['class'];
            } else {
                $modules[$id] = Craft::t('app', 'Unknown type');
            }
        }

        $aliases = [];
        foreach (Craft::$aliases as $alias => $value) {
            if (is_array($value)) {
                foreach ($value as $a => $v) {
                    $aliases[$a] = $v;
                }
            } else {
                $aliases[$alias] = $value;
            }
        }
        ksort($aliases);

        return Craft::$app->getView()->renderTemplate('_components/utilities/SystemReport', [
            'appInfo' => self::_appInfo(),
            'plugins' => Craft::$app->getPlugins()->getAllPlugins(),
            'modules' => $modules,
            'aliases' => $aliases,
            'requirements' => self::_requirementResults(),
        ]);
    }

    /**
     * Returns application info.
     *
     * @return array
     */
    private static function _appInfo(): array
    {
        return [
            'PHP version' => App::phpVersion(),
            'OS version' => PHP_OS . ' ' . php_uname('r'),
            'Database driver & version' => self::_dbDriver(),
            'Image driver & version' => self::_imageDriver(),
            'Craft edition & version' => 'Craft ' . App::editionName(Craft::$app->getEdition()) . ' ' . Craft::$app->getVersion(),
            'Yii version' => Yii::getVersion(),
            'Twig version' => Environment::VERSION,
            'Guzzle version' => Client::VERSION,
        ];
    }

    /**
     * Returns the DB driver name and version
     *
     * @return string
     */
    private static function _dbDriver(): string
    {
        $db = Craft::$app->getDb();

        if ($db->getIsMysql()) {
            $driverName = 'MySQL';
        } else {
            $driverName = 'PostgreSQL';
        }

        return $driverName . ' ' . App::normalizeVersion($db->getSchema()->getServerVersion());
    }

    /**
     * Returns the image driver name and version
     *
     * @return string
     */
    private static function _imageDriver(): string
    {
        $imagesService = Craft::$app->getImages();

        if ($imagesService->getIsGd()) {
            $driverName = 'GD';
        } else {
            $driverName = 'Imagick';
        }

        return $driverName . ' ' . $imagesService->getVersion();
    }

    /**
     * Runs the requirements checker and returns its results.
     *
     * @return array
     */
    private static function _requirementResults(): array
    {
        $reqCheck = new RequirementsChecker();
        $reqCheck->checkCraft();

        return $reqCheck->getResult()['requirements'];
    }
}
