<?php

namespace DevGroup\ServerPush;

use yii;
use yii\helpers\ArrayHelper;
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
     *
     * @throws \yii\base\InvalidParamException
     */
    public function bootstrap($app)
    {
        // skip non-web applications
        if ($app instanceof Application === false) {
            return;
        }
        $app->on(Application::EVENT_BEFORE_ACTION, function() {
            // attach event to View when all scripts and styles are present
            Yii::$app->view->on(View::EVENT_END_PAGE, function () {
                /** @var View $view */
                $view = Yii::$app->view;

                // as \yii\web\View doesn't have events on registering css & js
                // we have to parse ready tags
                // the better performance solution will be to override View
                // but that is a task for next version
                if ($view->cssFiles !== null && count($view->cssFiles) > 0) {
                    $cssLinkTags = implode(' ', $view->cssFiles);
                    preg_match_all('/href="([^"]*)"/', $cssLinkTags, $matches);
                    if (array_key_exists(1, $matches)) {
                        foreach ($matches[1] as $filename) {
                            $this->addPreloadHeader($filename, 'style');
                        }
                    }
                }

                /**
                 * By default we push only HEAD scripts.
                 * You can add other positions in your app config.php like this:
                 * ```
                 * 'params' => [
                 *      'serverPush' => [
                 *          'scriptsPositions' => [
                 *              \yii\web\View::POS_HEAD,
                 *              \yii\web\View::POS_BEGIN, // right after <body>
                 *              \yii\web\View::POS_END,   // right before </body>
                 *          ],
                 *      ],
                 * ],
                 * ```
                 */
                $pushScriptsOnPosition = ArrayHelper::getValue(
                    Yii::$app->params,
                    'serverPush.scriptsPositions',
                    [
                        View::POS_HEAD
                    ]
                );

                foreach ($pushScriptsOnPosition as $position) {
                    if (is_array($view->jsFiles) AND array_key_exists($position, $view->jsFiles) && $view->jsFiles[$position] !== null && count($view->jsFiles[$position]) > 0) {
                        $jsFiles = implode(' ', $view->jsFiles[$position]);
                        preg_match_all('/src="([^"]*)"/', $jsFiles, $matches);

                        if (array_key_exists(1, $matches)) {
                            foreach ($matches[1] as $filename) {
                                $this->addPreloadHeader($filename, 'script');
                            }
                        }
                    }
                }

                /**
                 * You can add additional resources for preloading using App params.
                 * For example, modify your config.php file:
                 * 'params' => [
                 *      'serverPush' => [
                 *          'additionalResources' => [
                 *              '/images/logo.png' => 'image',
                 *              '/styles.min.css' => 'style',
                 *              '/app.js' => 'script',
                 *          ],
                 *      ],
                 * ],
                 * ```
                 *
                 * Tip: You can do it during your action by accessing `Yii::$app->params`.
                 */
                $additionalResources = ArrayHelper::getValue(
                    Yii::$app->params,
                    'serverPush.additionalResources',
                    []
                );
                if (count($additionalResources) > 0) {
                    foreach ($additionalResources as $filename => $as) {
                        $this->addPreloadHeader($filename, $as);
                    }
                }
            });
        });
    }

    /**
     * @param string $filename Filename
     * @param string $as Type of resource(script/style/image)
     */
    protected function addPreloadHeader($filename, $as)
    {
        $cross = strpos($filename, '//') === 0 ? '; crossorigin' : '';
        $cross = strpos($filename, 'http') === 0 ? '; crossorigin' : $cross;
        Yii::$app->response->headers->add(
            'Link',
            "<$filename>; rel=preload; as=$as" . $cross
        );
    }
}
