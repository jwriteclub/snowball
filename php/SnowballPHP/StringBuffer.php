<?php

namespace SnowballPHP;

class StringBuffer implements CharSequence {

    protected $curr;
    protected $arrlen;
    protected $len = 0;

    /**
     * @param $arg  null|int|string
     */
    public function __construct($arg = null) {
        if ($arg == null) {
            $len = 0; // Make a 64 character buffer, if we don't have anything specified
        } else if (is_string($arg)) {
            $len = 0; // In this case, we'll set it up when we insert
        } else if (is_int($arg)) {
            $len = 0; // In this case, don't do anything
        } else {
            throw new \Exception("Unexpected type argument");
        }
        $this->curr = array();
        $this->arrlen = $len;
        if (is_string($arg)) {
            $this->insert(0, $arg);
        }
    }

    public function append($item) {
        $this->insert($this->len, $item);
        return $this;
    }

    public function insert($position, $item) {
        $position = intval($position);
        if ($item instanceof StringBuffer) {
            $items = $item->curr;
        } else {
            $item = (string)$item;
            if ($position > $this->len) {
                throw new \Exception("Trying to insert beyond the end of the buffer");
            }
            if ($position < 0) {
                throw new \Exception("Negative position");
            }
            $items = array();
            $pos = 0;
            while ($pos < strlen($item)) {
                array_push($items, $this->decodeChar($item, $pos));
            }
            //echo "Got " . count($items) . " characters from " . strlen($item) . " bytes" . PHP_EOL;
        }
        $move = $this->len - $position;
        $insertCount = count($items);

        //echo "Need to move $move chars".PHP_EOL;
        if ($move > 0) {
            //echo "Start ".($this->len + $insertCount - 1).", Limit: ".($position + $insertCount).PHP_EOL;
            for ($i = $this->len + $insertCount - 1; $i >= $position + $insertCount; $i -= 1) {
                //echo "Moving ".($i - $insertCount)." to ".$i." ".$this->encodeChar($this->curr[$i - $insertCount]).PHP_EOL;
                $this->curr[$i] = $this->curr[$i - $insertCount];
            }
        }

        for($i = 0; $i < $insertCount; $i += 1) {
            $this->curr[$i + $position] = $items[$i];
        }
        $this->len += $insertCount;
        return $this;
    }

    public function replace($start, $end, $item) {
        if ($item instanceof StringBuffer) {
            $items = $item->curr;
        } else {
            $item = (string)$item;
            // TODO: Bounds checking
            $items = array();
            $pos = 0;
            while ($pos < strlen($item)) {
                array_push($items, $this->decodeChar($item, $pos));
            }
            //echo "Got " . count($items) . " characters from " . strlen($item) . " bytes" . PHP_EOL;
        }
        if (isset($this->curr[$end])) {
            $limit = $end - $start;
        } else {
            $limit = count($items);
        }
        if (count($items) < $limit) {
            $limit = count($items);
        }
        //echo "Limit is: ".$limit.PHP_EOL;
        for ($i = 0; $i < $limit; $i += 1) {
            $this->curr[$i + $start] = $items[$i];
        }
        for($i = $start + $limit; $i < $this->len; $i += 1) {
            if (isset($this->curr[$i])) unset($this->curr[$i]); // Delete removed elements
        }
        $this->len = $start + $limit;
        return $this;
    }

    public function charAt($index) {
        if ($index >= $this->len) {
            throw new \Exception("Index beyond the end of the buffer");
        }
        if ($index < 0) {
            throw new \Exception("Index beyond the end of the buffer");
        }
        return $this->encodeChar($this->curr[$index]);
    }

    public function intAt($index) {
        if ($index >= $this->len) {
            throw new \Exception("Index beyond the end of the buffer");
        }
        if ($index < 0) {
            throw new \Exception("Index beyond the end of the buffer");
        }
        return $this->curr[$index];
    }

    public function length() {
        return $this->len;
    }

    public function substring($start, $end = -1) {
        if ($end = -1) {
            $end = $this->len;
        }
        return $this->subSequence($start, $end);
    }

    public function subSequence($start, $end) {
        $ret = new StringBuffer();
        $limit = $end - $start;
        for ($i = 0; $i < $limit; $i += 1) {
            $ret->curr[$i] = $this->curr[$i + $start];
        }
        $ret->len = $limit;
        return $ret;
    }

    public function __toString() {
        $ret = "";
        for ($i = 0; $i < $this->len; $i += 1) {
            $ret .= $this->encodeChar($this->curr[$i]);
        }
        return $ret;
    }

    protected function encodeChar($int) {
        if ($int <= 0xFF) {
            return chr($int); // 1 byte
        }
        return $this->encodeChar(42); // The star is at the same point in most 8 bit code tables
    }

    protected function decodeChar($input, &$pos = 0) {
        $len = strlen($input);
        if ($pos + 1 > $len) {
            //echo "End of stream".PHP_EOL;
            return 0;
        }
        $first = ord($input[$pos]);
        $pos += 1;
        return $first;
    }

}