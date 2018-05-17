<?php
/**
 * Created by PhpStorm.
 * User: makeilalundy
 * Date: 5/17/18
 * Time: 9:24 AM
 */


class ViewTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */

    // tests
    public function testNormalizeObjectTemplate()
    {
        $view = Craft::$app->view;

        $this->assertEquals( '{{ object.titleWithHyphens|replace({\'-\': \'!\'}) }}', $view->normalizeObjectTemplate('{{ object.titleWithHyphens|replace({\'-\': \'!\'}) }}'));
        $this->assertEquals( '{{object.foo|raw}}', $view->normalizeObjectTemplate('{foo}'));
        $this->assertEquals( '{{object.foo.bar|raw}}', $view->normalizeObjectTemplate('{foo.bar}'));
       // $this->assertEquals( '{foo : \'bar\'}', $view->normalizeObjectTemplate('{foo : \'bar\'}'));
        $this->assertEquals( '{{foo}}', $view->normalizeObjectTemplate('{{foo}}'));
        $this->assertEquals( '{% foo %}', $view->normalizeObjectTemplate('{% foo %}'));
        $this->assertEquals( '{{object.foo.fn({bar: baz})|raw}}', $view->normalizeObjectTemplate('{foo.fn({bar: baz})}'));
        $this->assertEquals( '{{object.foo.fn({bar: {baz: 1}})|raw}}', $view->normalizeObjectTemplate('{foo.fn({bar: {baz: 1}})}'));
        $this->assertEquals( '{{object.foo.fn(\'bar:baz\')|raw}}', $view->normalizeObjectTemplate('{foo.fn(\'bar:baz\')}'));
        $this->assertEquals( '{{object.foo.fn({\'bar\': baz})|raw}}', $view->normalizeObjectTemplate('{foo.fn({\'bar\': baz})}'));















    }

}