<?php

namespace DevGroup\ServerPush;

use yii;
use yii\web\Application;
use yii\web\View;

/**
 * Class AutomaticServerPush adds an event before page rendering end for adding needed http2 server push headers.
 *
 * @package DevGroup\ServerPush
 */
class AutomaticServerPush implements yii\base\BootstrapInterface
{

    /**
     * Bootstrap method to be called during application bootstrap stage.
     *
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_ACTION, function() {
            Yii::$app->view->on(View::EVENT_END_PAGE, function () {
                /** @var View $view */
                $view = Yii::$app->view;
                $cssLinkTags = implode(' ', $view->cssFiles);
                preg_match_all('/href="([^"]*)"/', $cssLinkTags, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $cssFile) {
                        $cross = strpos($cssFile, '//') !== false ? '; crossorigin' : '';
                        $cross = strpos($cssFile, 'http') !== false ? '; crossorigin' : $cross;
                        Yii::$app->response->headers->add(
                            'Link',
                            "<$cssFile>; rel=preload; as=style" . $cross
                        );
                    }
                }
                if (isset($view->jsFiles[View::POS_HEAD])) {
                    $jsFiles = implode(' ', $view->jsFiles[View::POS_HEAD]);
                    preg_match_all('/src="([^"]*)"/', $jsFiles, $matches);
                    if (isset($matches[1])) {
                        foreach ($matches[1] as $cssFile) {
                            $cross = strpos($cssFile, '//') !== false ? '; crossorigin' : '';
                            $cross = strpos($cssFile, 'http') !== false ? '; crossorigin' : $cross;
                            Yii::$app->response->headers->add(
                                'Link',
                                "<$cssFile>; rel=preload; as=script" . $cross
                            );
                        }
                    }
                }
            });
        });
    }
}
