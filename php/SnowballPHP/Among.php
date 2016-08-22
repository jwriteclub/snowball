<?php

namespace SnowballPHP;

class Among {

    /** @var CharSequence */
    public $s;
    /** @var int */
    public $substring_i;
    /** @var int */
    public $result;
    /** @var \ReflectionMethod */
    public $method = null;

    public function __construct($s, $substring_i, $result, $methodname = null, $programClass = null) {
        $this->s = new StringBuffer($s);
        $this->substring_i = $substring_i;
        $this->result = $result;
        if ($methodname != null && $programClass != null) {
            try {
                $rc = new \ReflectionClass($programClass);
                $this->method = $rc->getMethod($methodname);
                if ($this->method == null) {
                    throw new \Exception("No such method $methodname");
                }
            } catch (\Exception $ex) {
                throw new \Exception($ex);
            }
        }
    }

    public function __toString() {
        return $this->s->__toString();
    }

    public function __debugInfo() {
        return array("s" => $this->s->__toString(), "substring_i" => $this->substring_i, "result" => $this->result, "dynamic" => $this->method != null);
    }
}