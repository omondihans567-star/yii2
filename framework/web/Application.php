<?php
<?php

namespace yii\web;

use Yii;
use yii\base\Application as BaseApplication;
use yii\base\InvalidConfigException;
use yii\web\Request;
use yii\web\Response;
use yii\web\ErrorHandler;

/**
 * Enhanced Web Application Class
 * 
 * This class extends the base Application to provide enhanced functionality
 * including better error handling, logging, and execution flow control.
 * 
 * @property Request $request The request component
 * @property Response $response The response component
 * @property ErrorHandler $errorHandler The error handler component
 */
class Application extends BaseApplication
{
    /**
     * @var string|null The application name
     */
    public $name = 'Web Application';

    /**
     * @var string|null The application version
     */
    public $version = null;

    /**
     * Executes the application
     * 
     * This method handles the complete application lifecycle:
     * 1. Initializes the application
     * 2. Processes the request
     * 3. Handles responses
     * 4. Manages errors gracefully
     * 5. Cleans up resources
     * 
     * @throws InvalidConfigException if the application is not properly configured
     * @return int Exit status code (0 for success, non-zero for errors)
     */
    public function run()
    {
        try {
            // Initialize the application components
            $this->init();
            
            // Log application start
            Yii::info("Starting application execution: {$this->name}", __METHOD__);
            
            // Process the request and generate response
            $response = $this->handleRequest($this->getRequest());
            
            // Send the response to client
            $response->send();
            
            // Log successful completion
            Yii::info("Application execution completed successfully", __METHOD__);
            
            return 0;
            
        } catch (\Throwable $exception) {
            // Handle any uncaught exceptions
            return $this->handleException($exception);
        } finally {
            // Always perform cleanup operations
            $this->cleanup();
        }
    }

    /**
     * Handles uncaught exceptions during application execution
     * 
     * This method provides graceful error handling with detailed logging
     * and appropriate HTTP responses based on the environment.
     * 
     * @param \Throwable $exception The uncaught exception
     * @return int Exit status code
     */
    protected function handleException(\Throwable $exception)
    {
        try {
            // Log the exception details
            Yii::error("Application exception occurred: " . $exception->getMessage(), __METHOD__);
            Yii::error("Exception trace: " . $exception->getTraceAsString(), __METHOD__);
            
            // Get error handler component
            $errorHandler = $this->getErrorHandler();
            
            // If error handler exists, use it
            if ($errorHandler instanceof ErrorHandler) {
                $errorHandler->handleException($exception);
            } else {
                // Fallback to basic error display
                $this->displayError($exception);
            }
            
            return 1;
            
        } catch (\Throwable $e) {
            // If even error handling fails, log and display minimal info
            echo "Critical error in exception handling: " . $e->getMessage();
            Yii::error("Critical error in exception handling: " . $e->getMessage(), __METHOD__);
            return 2;
        }
    }

    /**
     * Displays error information to the client
     * 
     * @param \Throwable $exception The exception to display
     */
    protected function displayError(\Throwable $exception)
    {
        // In development mode, show detailed error
        if (YII_DEBUG) {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else {
            // In production, show generic error
            http_response_code(500);
            echo "<h1>Internal Server Error</h1>";
            echo "<p>We're sorry, but something went wrong.</p>";
        }
    }

    /**
     * Performs cleanup operations after application execution
     * 
     * This ensures proper resource management and memory cleanup.
     */
    protected function cleanup()
    {
        try {
            // Log application shutdown
            Yii::info("Performing application cleanup", __METHOD__);
            
            // Close database connections if they exist
            if (isset($this->db)) {
                $this->db->close();
            }
            
            // Clear any cached data that might be lingering
            Yii::getLogger()->flush();
            
            // Perform any additional cleanup tasks
            $this->trigger(self::EVENT_AFTER_REQUEST);
            
        } catch (\Throwable $exception) {
            Yii::warning("Cleanup failed: " . $exception->getMessage(), __METHOD__);
        }
    }

    /**
     * Gets the request component
     * 
     * @return Request The request component
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * Gets the response component
     * 
     * @return Response The response component
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * Gets the error handler component
     * 
     * @return ErrorHandler The error handler component
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }

    /**
     * Enhanced initialization method
     * 
     * Provides better error handling during initialization phase
     */
    public function init()
    {
        parent::init();
        
        // Validate required components
        $requiredComponents = ['request', 'response', 'errorHandler'];
        foreach ($requiredComponents as $component) {
            if (!$this->has($component)) {
                throw new InvalidConfigException("Required component '$component' is not configured.");
            }
        }
        
        // Set application properties
        if ($this->version !== null) {
            Yii::setAlias('@appVersion', $this->version);
        }
        
        // Register shutdown function for cleanup
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    /**
     * Shutdown handler for fatal errors
     */
    public function shutdownHandler()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            Yii::error("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}", __METHOD__);
        }
    }

    /**
     * Enhanced request handling with additional logging and validation
     * 
     * @param Request $request The request to handle
     * @return Response The response generated
     */
    public function handleRequest($request)
    {
        if ($request === null) {
            throw new InvalidConfigException('The request component is not configured.');
        }
        
        // Log request processing
        Yii::info("Handling request: " . $request->getUrl(), __METHOD__);
        
        // Process the request through the application flow
        $response = parent::handleRequest($request);
        
        // Validate response
        if (!$response instanceof Response) {
            throw new InvalidConfigException('The response component must be an instance of Response.');
        }
        
        return $response;
    }
}

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\InvalidRouteException;
use yii\helpers\Url;

/**
 * Application is the base class for all web application classes.
 *
 * For more details and usage information on Application, see the [guide article on applications](guide:structure-applications).
 *
 * @template TUserIdentity of IdentityInterface = IdentityInterface
 *
 * @property-read ErrorHandler $errorHandler The error handler application component.
 * @property string $homeUrl The homepage URL.
 * @property-read Request $request The request component.
 * @property-read Response $response The response component.
 * @property-read Session $session The session component.
 * @property-read User<TUserIdentity> $user The user component.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Application extends \yii\base\Application
{
    /**
     * @var string the default route of this application. Defaults to 'site'.
     */
    public $defaultRoute = 'site';
    /**
     * @var array|null the configuration specifying a controller action which should handle
     * all user requests. This is mainly used when the application is in maintenance mode
     * and needs to handle all incoming requests via a single action.
     * The configuration is an array whose first element specifies the route of the action.
     * The rest of the array elements (key-value pairs) specify the parameters to be bound
     * to the action. For example,
     *
     * ```
     * [
     *     'offline/notice',
     *     'param1' => 'value1',
     *     'param2' => 'value2',
     * ]
     * ```
     *
     * Defaults to null, meaning catch-all is not used.
     */
    public $catchAll;
    /**
     * @var Controller|null the currently active controller instance
     */
    public $controller;


    /**
     * {@inheritdoc}
     */
    protected function bootstrap()
    {
        $request = $this->getRequest();
        Yii::setAlias('@webroot', dirname($request->getScriptFile()));
        Yii::setAlias('@web', $request->getBaseUrl());

        parent::bootstrap();
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled
     * @return Response the resulting response
     * @throws NotFoundHttpException if the requested route is invalid
     */
    public function handleRequest($request)
    {
        if (empty($this->catchAll)) {
            try {
                list($route, $params) = $request->resolve();
            } catch (UrlNormalizerRedirectException $e) {
                $url = $e->url;
                if (is_array($url)) {
                    if (isset($url[0])) {
                        // ensure the route is absolute
                        $url[0] = '/' . ltrim($url[0], '/');
                    }
                    $url += $request->getQueryParams();
                }

                return $this->getResponse()->redirect(Url::to($url, $e->scheme), $e->statusCode);
            }
        } else {
            $route = $this->catchAll[0];
            $params = $this->catchAll;
            unset($params[0]);
        }
        try {
            Yii::debug("Route requested: '$route'", __METHOD__);
            $this->requestedRoute = $route;
            $result = $this->runAction($route, $params);
            if ($result instanceof Response) {
                return $result;
            }

            $response = $this->getResponse();
            if ($result !== null) {
                $response->data = $result;
            }

            return $response;
        } catch (InvalidRouteException $e) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'), $e->getCode(), $e);
        }
    }

    private $_homeUrl;

    /**
     * @return string the homepage URL
     */
    public function getHomeUrl()
    {
        if ($this->_homeUrl === null) {
            if ($this->getUrlManager()->showScriptName) {
                return $this->getRequest()->getScriptUrl();
            }

            return $this->getRequest()->getBaseUrl() . '/';
        }

        return $this->_homeUrl;
    }

    /**
     * @param string $value the homepage URL
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }

    /**
     * Returns the error handler component.
     * @return ErrorHandler the error handler application component.
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }

    /**
     * Returns the request component.
     * @return Request the request component.
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * Returns the response component.
     * @return Response the response component.
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * Returns the session component.
     * @return Session the session component.
     */
    public function getSession()
    {
        return $this->get('session');
    }

    /**
     * Returns the user component.
     * @return User<TUserIdentity> the user component.
     */
    public function getUser()
    {
        return $this->get('user');
    }

    /**
     * {@inheritdoc}
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'request' => ['class' => 'yii\web\Request'],
            'response' => ['class' => 'yii\web\Response'],
            'session' => ['class' => 'yii\web\Session'],
            'user' => ['class' => 'yii\web\User'],
            'errorHandler' => ['class' => 'yii\web\ErrorHandler'],
        ]);
    }
}
