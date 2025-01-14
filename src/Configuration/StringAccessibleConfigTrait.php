<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Configuration;

use App\Entity\Configuration;

/**
 * @internal do NOT use this trait, but access your configs via SystemConfiguration
 */
trait StringAccessibleConfigTrait
{
    /**
     * @var array
     */
    protected $settings;
    /**
     * @var array
     */
    protected $original;
    /**
     * @var ConfigLoaderInterface
     */
    protected $repository;
    /**
     * @var bool
     */
    protected $initialized = false;

    public function __construct(ConfigLoaderInterface $repository, array $settings)
    {
        $this->repository = $repository;
        $this->original = $this->settings = $settings;
    }

    /**
     * @param ConfigLoaderInterface $repository
     * @return Configuration[]
     */
    protected function getConfigurations(ConfigLoaderInterface $repository): array
    {
        return $repository->getConfiguration($this->getPrefix());
    }

    protected function prepare()
    {
        if ($this->initialized) {
            return;
        }

        // this foreach should be replaced by a better piece of code,
        // especially the pointers could be a problem in the future
        foreach ($this->getConfigurations($this->repository) as $configuration) {
            $temp = explode('.', $configuration->getName());
            $this->setConfiguration($temp, $configuration->getValue());
        }

        $this->initialized = true;
    }

    private function setConfiguration(array $keys, ?string $value): void
    {
        $array = &$this->settings;
        if ($keys[0] === $this->getPrefix()) {
            $keys = \array_slice($keys, 1);
        }
        foreach ($keys as $key2) {
            if (!\array_key_exists($key2, $array)) {
                $array[$key2] = $value;
                continue;
            }
            if (\is_array($array[$key2])) {
                $array = &$array[$key2];
            } elseif (\is_bool($array[$key2])) {
                $array[$key2] = (bool) $value;
            } elseif (\is_int($array[$key2])) {
                $array[$key2] = (int) $value;
            } else {
                $array[$key2] = $value;
            }
        }
    }

    /**
     * @return string
     */
    abstract protected function getPrefix(): string;

    /**
     * @param string $key
     * @return mixed
     */
    public function default(string $key)
    {
        $key = $this->prepareSearchKey($key);

        return $this->get($key, $this->original);
    }

    /**
     * @param string $key
     * @return string|int|bool|float|null|array
     */
    public function find(string $key)
    {
        $this->prepare();
        $key = $this->prepareSearchKey($key);

        return $this->get($key, $this->settings);
    }

    private function prepareSearchKey(string $key): string
    {
        $prefix = $this->getPrefix() . '.';
        $length = \strlen($prefix);

        if (substr($key, 0, $length) === $prefix) {
            $key = substr($key, $length);
        }

        return $key;
    }

    /**
     * @param string $key
     * @param array $config
     * @return mixed
     */
    private function get(string $key, array $config)
    {
        $keys = explode('.', $key);
        $search = array_shift($keys);

        if (!\array_key_exists($search, $config)) {
            return null;
        }

        if (\is_array($config[$search]) && !empty($keys)) {
            return $this->get(implode('.', $keys), $config[$search]);
        }

        return $config[$search];
    }

    public function has(string $key): bool
    {
        $this->prepare();
        $key = $this->prepareSearchKey($key);

        $keys = explode('.', $key);
        $search = array_shift($keys);

        if (!\array_key_exists($search, $this->settings)) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->find($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws \BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        $this->setConfiguration(explode('.', $offset), $value);
    }

    /**
     * @param mixed $offset
     * @throws \BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('SystemBundleConfiguration does not support offsetUnset()');
    }
}
