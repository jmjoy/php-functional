<?php

namespace functional;

use ArrayAccess;
use Countable;

/**
 * Enhanced ArrayObject
 */
class ArrayObject extends \ArrayObject {

    public function toArray() {
        return (array) $this;
    }

    public function toJson($options = 0) {
        return json_encode($this->toArray(), $options);
    }

	public function get($key, $default = null, $callback = null) {
        if ($this->isArray($key)) {
            $value = &$this;
            for ($i = 0, $count = count($key); $i < $count; $i += 1) {
                if ($this->isArray($value) && isset($value[$key[$i]])) {
                    $value = &$value[$key[$i]];
                } else {
                    $value = $default;
                    break;
                }
            }
        } else {
            $value = isset($this[$key]) ? $this[$key] : $default;
        }

		return ($callback === null) ? $value : $this->callFunc($callback, array($value));
	}

    public function set($key, $value) {
        if ($this->isArray($key)) {
            $ref = &$this;
            for ($i = 0, $count = count($key); $i < $count; $i += 1) {
                $ref = &$ref[$key[$i]];
            }
            $ref = $value;
        } else {
            $this[$key] = $value;
        }
        return $this;
    }

    public function has($key) {
        if ($this->isArray($key)) {
            $ref = &$this;
            for ($i = 0, $count = count($key); $i < $count; $i += 1) {
                if (!$this->isArray($ref) || !isset($ref[$key[$i]])) {
                    return false;
                }
                $ref = &$ref[$key[$i]];
            }
            return true;
        }
        return isset($this[$key]);
    }

    public function remove($key) {
        if ($this->isArray($key)) {
            $ref = &$this;
            for ($i = 0, $count = count($key); $i < $count - 1; $i += 1) {
                if (!$this->isArray($ref) || !isset($ref[$key[$i]])) {
                    return $this;
                }
                $ref = &$ref[$key[$i]];
            }
            if ($this->isArray($ref) && isset($ref[$key[count($key) - 1]])) {
                unset($ref[$key[count($key) - 1]]);
            }
        } else {
            if (isset($this[$key])) {
                unset($this[$key]);
            }
        }
        return $this;
    }

    public function in($value, $strict = false) {
        return in_array($value, $this->toArray(), $strict);
    }

    public function sort($flag = SORT_REGULAR) {
        $array = $this->toArray();
        if (is_int($flag)) {
            sort($array, $flag);
        } else {
            usort($array, $flag);
        }
        return new static($array);
    }

    public function sortByFields($keys) {
        return $this->sort(function($left, $right) use ($keys) {
            foreach ($keys as $key => $sort) {
                $leftValue = !empty($left[$key]) ? $left[$key] : 0;
                $rightValue = !empty($right[$key]) ? $right[$key] : 0;

                if ($leftValue != $rightValue) {
                    if ($sort == SORT_DESC) {
                        return $leftValue < $rightValue ? 1 : -1;
                    }
                    return $leftValue > $rightValue ? 1 : -1;
                }
            }
            return 0;
        });
    }

    public function merge($array) {
        if ($array instanceof Arr || $array instanceof ArrayObject) {
            $array = $array->toArray();
        }
        return new static(array_merge($this->toArray(), $array));
    }

    public function each($callback) {
        foreach ($this as $key => $value) {
            $result = call_user_func($callback, $value, $key);
            if ($result === false) {
                break;
            }
        }
    }

    public function keys() {
        $instance = new static();
        foreach ($this as $key => $_) {
            $instance[] = $key;
        }
        return $instance;
    }

    public function values() {
        $instance = new static();
        foreach ($this as $value) {
            $instance[] = $value;
        }
        return $instance;
    }

    public function chunk($size) {
        $instance = new static();

        $tmp = new static();
        foreach ($this as $value) {
            if ($tmp->count() >= $size) {
                $instance[] = $tmp;
                $tmp = new static();
            }
            $tmp[] = $value;
        }
        $instance[] = $tmp;

        return $instance;
    }

    public function column($field) {
        $instance = new static();
        foreach ($this as $key => $value) {
            if ($this->isArray($field)) {
                for ($i = 0, $count = count($field); $i < $count; $i += 1) {
                    $value = &$value[$field[$i]];
                }
                $instance[$key] = $value;
            } else {
                $instance[$key] = $value[$field];
            }
        }
        return $instance;
    }

    public function combine($array) {
        $instance = new static();

        reset($this);
        reset($array);

        while (($key = each($this)) && ($value = each($array))) {
            $instance[$key[1]] = $value[1];
        }

        return $instance;
    }

    public function min() {
        $min = reset($this);
        while (($x = next($this)) !== false) {
            if ($min > $x) {
                $min = $x;
            }
        }
        return $min;
    }

    public function max() {
        $max = reset($this);
        while (($x = next($this)) !== false) {
            if ($max < $x) {
                $max = $x;
            }
        }
        return $max;
    }

    public function reverse() {
        $instance = new static();
        end($this);
        while (key($this) !== null) {
            $instance[] = current($this);
            prev($this);
        }
        return $instance;
    }

    public function map($callback) {
        $instance = new static();
        foreach ($this as $key => $value) {
            $result = call_user_func($callback, $value, $key);
            if ($result instanceof KeyValue) {
                $instance[$result->key] = $result->value;
            } else {
                $instance[$key] = $result;
            }
        }
        return $instance;
    }

    public function filter($callback) {
        $instance = new static();
        foreach ($this as $key => $value) {
            if ($this->callFunc($callback, array($value, $key))) {
                $instance[$key] = $value;
            }
        }
        return $instance;
    }

    public function foldl($callback, $initial = null) {
        $result = $initial;
        foreach ($this as $key => $value) {
            $result = $this->callFunc($callback, array($result, $value, $key));
        }
        return $result;
    }

    public function foldr($callback, $initial = null) {
        $result = $initial;

        end($this);
        while (true) {
            $key = key($this);
            if ($key === null) {
                break;
            }
            $value = current($this);
            $result = $this->callFunc($callback, array($result, $value, $key));

            prev($this);
        }

        return $result;
    }

    public function keyBy($index) {
        $instance = new static();

        foreach ($this as $key => $value) {
            if (is_string($index)) {
                $instance[$value[$index]] = $value;
            } else {
                $instance[$this->callFunc($index, array($value, $key))] = $value;
            }
        }

        return $instance;
    }

    public function all($callback = null) {
        foreach ($this as $key => $value) {
            if (!($callback ? $this->callFunc($callback, array($value, $key)) : $value)) {
                return false;
            }
        }
        return true;
    }

    public function any($callback = null) {
        foreach ($this as $key => $value) {
            if ($callback ? $this->callFunc($callback, array($value, $key)) : $value) {
                return true;
            }
        }
        return false;
    }

    public function sum() {
        $sum = 0;
        foreach ($this as $value) {
            $sum += $value;
        }
        return $sum;
    }

    public function product() {
        $sum = 1;
        foreach ($this as $value) {
            $sum *= $value;
        }
        return $sum;
    }

    public function flatten() {
        $instance = new static();
        foreach ($this as $key => $row) {
            if ($this->isArray($row)) {
                foreach ($row as $value) {
                    $instance[] = $value;
                }
            } else {
                $instance[] = $row;
            }
        }
        return $instance;
    }

    public function first($callback, $returnKeyValue = false) {
        foreach ($this as $key => $value) {
            if ($this->callFunc($callback, array($value, $key))) {
                if ($returnKeyValue) {
                    return new KeyValue($key, $value);
                }
                return $value;
            }
        }
        if ($returnKeyValue) {
            return new KeyValue(null, null);
        }
        return null;
    }

    public function only($keys) {
        $instance = new static();
        foreach ($this as $key => $value) {
            if (in_array($key, $keys)) {
                $instance[$key] = $value;
            }
        }
        return $instance;
    }

    public function except($keys) {
        $instance = new static();
        foreach ($this as $key => $value) {
            if (!in_array($key, $keys)) {
                $instance[$key] = $value;
            }
        }
        return $instance;
    }

    // TODO Next version
    // public function take($n) {
    //     $instance = new static();
    //     $i = 0;
    //     $count = count($this);
    //     $is_int = is_int($n);

    //     while (true) {
    //         if ($i >= $count) {
    //             break;
    //         }
    //         if ($is_int) {
    //             if ($i >= $n) {
    //                 break;
    //             }
    //         } else {
    //             if (!$this->callFunc($n, array($this[$i], $i))) {
    //                 break;
    //             }
    //         }
    //         $instance[] = $this[$i];
    //         $i += 1;
    //     }
    //     return $instance;
    // }

    // public function drop($n) {
    //     $i = 0;
    //     $count = count($this);
    //     $is_int = is_int($n);

    //     while (true) {
    //         if ($i >= $count) {
    //             break;
    //         }
    //         if ($is_int) {
    //             if ($i >= $n) {
    //                 break;
    //             }
    //         } else {
    //             if (!$this->callFunc($n, array($this[$i], $i))) {
    //                 break;
    //             }
    //         }
    //         $i += 1;
    //     }

    //     $instance = new static();
    //     for ($j = $i; $j <= $i; $i += 1) {
    //         $instance[] = $this[$i];
    //     }

    //     return $instance;
    // }

    protected function isArray($val) {
        return is_array($val) || ($val instanceof ArrayAccess && $val instanceof Countable);
    }

    protected function callFunc($callback, $params = array()) {
        return call_user_func_array($callback, $params);
    }

}
