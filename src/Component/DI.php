<?php

namespace Nanoframework\Component;

use \Psr\Container\ContainerInterface;

class DI implements ContainerInterface
{
    /**
     * Rules which have been set using addRule()
     * @var array
     */
    protected $rules = [];

    /**
     * A cache of closures based on class name so each class is only reflected once
     * @var array
     */
    protected $cache = [];

    /**
     * Stores any instances marked as 'shared' so create() can return the same instance
     * @var array
     */
    protected $instances = [];

    const CACHE_FILE_NAME = 'di.cache';

    public function __construct(array $rules = [], $cachePath = null)
    {
        $setCache = false;
        if (isset($cachePath)) {
            $this->rules = $this->getCache($cachePath . '/' . self::CACHE_FILE_NAME);
            $setCache = true;
        }
        if (empty($this->rules) && !empty($rules)) {
            $this->addRules($rules);
        }
        if ($setCache) {
            $this->setCache($cachePath . '/' . self::CACHE_FILE_NAME);
        }
    }

    /**
     * @param array $rules
     */
    public function addRules(array $rules = [])
    {
        foreach ($rules as $name => $rule) {
            $this->addRule($name, $rule);
        }
    }

    /**
     * Get cached rules
     *
     * @param string $path
     * @return array
     */
    public function getCache($path)
    {
        return include($path);
    }

    /**
     * Save rules to cache file
     *
     * @param string $path
     */
    public function setCache($path)
    {
        file_put_contents($path, '<?php return ' . var_export($this->rules, true) . ';');
    }


    /**
     * Add a rule $rule to the class $name see https://r.je/dice.html#example3 for $rule format
     *
     * @param string $name
     * @param array  $rule
     */
    public function addRule($name, array $rule)
    {
        $this->rules[ltrim(strtolower($name), '\\')] = array_merge($this->getRule($name), $rule);
    }

    /** returns the rule that will be applied to the class $name in create() */
    public function getRule($name)
    {
        $lcName = strtolower(ltrim($name, '\\'));
        if (isset($this->rules[$lcName])) {
            return $this->rules[$lcName];
        }
        
        foreach ($this->rules as $key => $rule) {
            // Find a rule which matches the class described in $name where:
            if (empty($rule['instanceOf'])     // It's not a named instance, the rule is applied to a class name
                && $key !== '*'                // It's not the default rule
                && is_subclass_of($name, $key) // The rule is applied to a parent class
                && (!array_key_exists('inherit', $rule) || $rule['inherit'] === true)
                // And that rule should be inherited to subclasses
            ) {
                return $rule;
            }
        }
        //No rule has matched, return the default rule if it's set
        return isset($this->rules['*']) ? $this->rules['*'] : [];
    }

    /** returns a fully constructed object based on $name using $args and $share as constructor arguments if supplied */
    public function get($name, array $args = [], array $share = [])
    {
        // Is there a shared instance set? Return it. Better here than a closure for this, calling a closure is slower.
        if (!empty($this->instances[$name])) {
            return $this->instances[$name];
        }

        // Create a closure for creating the object if there isn't one already
        if (empty($this->cache[$name])) {
            $this->cache[$name] = $this->getClosure($name, $this->getRule($name));
        }

        // Call the cached closure which will return a fully constructed object of type $name
        return $this->cache[$name]($args, $share);
    }

    public function has($name)
    {
        // TODO: implement
    }

    /** returns a closure for creating object $name based on $rule, caching the reflection object for later use */
    private function getClosure($name, array $rule)
    {
        // Reflect the class and constructor, this should only ever be done once per class and get cached
        $class = new \ReflectionClass(isset($rule['instanceOf']) ? $rule['instanceOf'] : $name);
        $constructor = $class->getConstructor();

        // Create parameter generating function in order to cache reflection on the parameters.
        // This way $reflect->getParameters() only ever gets called once
        if ($constructor) {
            $params = $this->getParams($constructor, $rule);
        } else {
            $params = null;
        }

        // Get a closure based on the type of object being created: Shared, normal or constructorless
        if (!empty($rule['shared'])) {
            $closure = function (array $args, array $share) use ($class, $name, $constructor, $params, $rule) {
                // Shared instance: create the class without calling the constructor
                // (and write to \$name and $name, see issue #68)
                $this->instances[$name] = $this->instances[ltrim($name, '\\')] = $class->newInstanceWithoutConstructor();
                if (!empty($rule['static'])) {
                    // TODO: rewrite static dependencies (must be avialable for any rules, not 'shared' only
                    $rfl = new \ReflectionMethod($this->instances[$name], $rule['static']);
                    $this->instances[$name] = $rfl->invokeArgs($this->instances[$name], $params($args, $share));
                } elseif ($constructor) {
                    // Now call this constructor after constructing all the dependencies.
                    // This avoids problems with cyclic references (issue #7)
                    $constructor->invokeArgs($this->instances[$name], $params($args, $share));
                }

                return $this->instances[$name];
            };
        } elseif ($params) {
            $closure = function (array $args, array $share) use ($class, $params) {
                //This class has depenencies, call the $params closure to generate them based on $args and $share
                return new $class->name(...$params($args, $share));
            };
        } else {
            $closure = function () use ($class) {
                //No constructor arguments, just instantiate the class
                return new $class->name;
            };
        }
        // If there are shared instances, create them and merge them with shared instances higher up the object graph
        if (isset($rule['shareInstances'])) {
            $closure = function (array $args, array $share) use ($closure, $rule) {
                return $closure($args, array_merge($args, $share, array_map([$this, 'get'], $rule['shareInstances'])));
            };
        }
        // When $rule['call'] is set, wrap the closure in another closure
        // which will call the required methods after constructing the object
        // By putting this in a closure, the loop is never executed unless call is actually set
        return isset($rule['call']) ? function (array $args, array $share) use ($closure, $class, $rule) {
            //Construct the object using the original closure
            $object = $closure($args, $share);

            foreach ($rule['call'] as $call) {
                //Generate the method arguments using getParams() and call the returned closure
                // (in php7 will be ()() rather than __invoke)
                $params = $this->getParams(
                    $class->getMethod($call[0]),
                    [
                        'shareInstances' => isset($rule['shareInstances']) ? $rule['shareInstances'] : []
                    ]
                )->__invoke($this->expand(isset($call[1]) ? $call[1] : []));
                $object->{$call[0]}(...$params);
            }
            return $object;
        } : $closure;
    }

    /** looks for 'instance' array keys in $param
     * and when found returns an object based
     * on the value see https://r.je/dice.html#example3-1
     */
    private function expand($param, array $share = [], $createFromString = false)
    {
        if (is_array($param) && isset($param['instance'])) {
            //Call or return the value sored under the key 'instance'
            //For ['instance' => ['className', 'methodName'] construct the instance before calling it
            if (is_array($param['instance'])) {
                $param['instance'][0] = $this->expand($param['instance'][0], $share, true);
            }
            if (is_callable($param['instance'])) {
                return call_user_func(
                    $param['instance'],
                    ...(isset($param['params']) ? $this->expand($param['params']) : [])
                );
            } else {
                return $this->get($param['instance'], $share);
            }
        } elseif (is_array($param)) {
            // Recursively search for 'instance' keys in $param
            foreach ($param as &$value) {
                $value = $this->expand($value, $share);
            }
        }

        // 'instance' wasn't found, return the value unchanged
        return is_string($param) && $createFromString ? $this->get($param) : $param;
    }

    /** returns a closure that generates arguments for $method based on $rule and any $args passed into the closure */
    private function getParams(\ReflectionMethod $method, array $rule)
    {
        //Cache some information about the parameter in $paramInfo so (slow) reflection isn't needed every time
        $paramInfo = [];
        /**
         * @var $param \ReflectionParameter
         */
        foreach ($method->getParameters() as $param) {
            $class = $param->getClass() ? $param->getClass()->name : null;
            $paramInfo[] = [
                $class,
                $param,
                isset($rule['substitutions']) && array_key_exists($class, $rule['substitutions'])
            ];
        }

        //Return a closure that uses the cached information to generate the arguments for the method
        return function (array $args, array $share = []) use ($paramInfo, $rule) {
            // Now merge all the possible parameters: user-defined in the rule via construct,
            // shared instances and the $args argument from $di->get();
            if ($share || isset($rule['construct'])) {
                $args = array_merge(
                    $args,
                    isset($rule['construct']) ? $this->expand($rule['construct'], $share) : [],
                    $share
                );
            }

            $parameters = [];

            // Now find a value for each method parameter
            foreach ($paramInfo as list($class, $param, $sub)) {
                /**
                 * @var $param \ReflectionParameter
                 */
                // First loop through $args and see whether or not
                // each value can match the current parameter based on type hint
                if ($args) { // This if statement actually gives a ~10% speed increase when $args isn't set
                    foreach ($args as $i => $arg) {
                        if ($class && ($arg instanceof $class || ($arg === null && $param->allowsNull()))) {
                            // The argument matched, store it and remove it from $args
                            // so it won't wrongly match another parameter
                            $parameters[] = array_splice($args, $i, 1)[0];
                            // Move on to the next parameter
                            continue 2;
                        }
                    }
                }

                if ($class) {
                    // When nothing from $args matches but a class is type hinted,
                    // create an instance to use, using a substitution if set
                    if ($sub) {
                        $parameters[] = $this->expand($rule['substitutions'][$class], $share, true);
                    } else {
                        $parameters[] = $this->get($class, [], $share);
                    }
                } elseif ($args) {
                    // There is no type hint, take the next available value from $args
                    // (and remove it from $args to stop it being reused)
                    $parameters[] = $this->expand(array_shift($args));
                } elseif ($param->isVariadic()) {
                    // For variadic parameters, provide remaining $args
                    $parameters = array_merge($parameters, $args);
                } elseif ($param->isDefaultValueAvailable()) {
                    // There's no type hint and nothing left in $args, provide the default value...
                    $parameters[] = $param->getDefaultValue();
                } else {
                    // ...or null
                    $parameters[] = null;
                }
            }
            // variadic functions will only have one argument.
            // To account for those, append any remaining arguments to the list
            return $parameters;
        };
    }
}
