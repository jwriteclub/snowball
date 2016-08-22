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
            $len = strlen($arg);
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
                array_push($items, $this->decodeUtf8Char($item, $pos));
            }
            //echo "Got " . count($items) . " characters from " . strlen($item) . " bytes" . PHP_EOL;
        }
        $move = $this->len - $position;
        $insertCount = count($items);

        //echo "Need to move $move chars".PHP_EOL;
        if ($move > 0) {
            //echo "Start ".($this->len + $insertCount - 1).", Limit: ".($position + $insertCount).PHP_EOL;
            for ($i = $this->len + $insertCount - 1; $i >= $position + $insertCount; $i -= 1) {
                //echo "Moving ".($i - $insertCount)." to ".$i." ".$this->encodeUtf8Char($this->curr[$i - $insertCount]).PHP_EOL;
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
                array_push($items, $this->decodeUtf8Char($item, $pos));
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
        return $this->encodeUtf8Char($this->curr[$index]);
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
            $ret .= $this->encodeUtf8Char($this->curr[$i]);
        }
        return $ret;
    }

    protected function encodeUtf8Char($int) {
        if ($int <= 0x7F) {
            return chr($int); // 1 byte ASCI
        }
        if ($int <= 0x7FF) {
            return chr(0b11000000 | ($int >> 6)).chr(0b10000000 | ($int & 0b00111111));
        }
        if ($int <= 0x7FFF) {
            return chr(0b11100000 | ($int >> 12)).chr(0b10000000 | (($int >> 6) & 0b00111111)).chr(0b10000000 | ($int & 0b00111111));
        }
        if ($int < 0x10FFFF) {
            return chr(0b11110000 | ($int >> 18)).chr(0b10000000 | (($int >> 12) & 0b00111111)).chr(0b10000000 | (($int >> 6) & 0b00111111)).chr(0b10000000 | ($int & 0b00111111));
        }
        return $this->encodeUtf8Char(65533);
    }

    protected function decodeUtf8Char($input, &$pos = 0) {
        $len = strlen($input);
        if ($pos + 1 > $len) {
            //echo "End of stream".PHP_EOL;
            return 0;
        }
        $first = ord($input[$pos]);
        $pos += 1;
        if ($first >> 7 == 0b0) {
            //echo "1 byte ASCI Code point ".chr($first)." ".$first.PHP_EOL;
            return $first;
        }
        if ($pos + 1 > $len) {
            //echo "2 byte UTF8 invalid (insufficient additional characters)".PHP_EOL;
            return 0;
        }
        if ($first >> 5 == 0b110) {
            $second = ord($input[$pos]);
            $pos += 1;
            if (($second >> 6) != 0b10) {
                //echo "2 byte UTF8 invalid (invalid second)".PHP_EOL;
                return 65533; // Unknown code point
            }
            $ret = (($first & 0b00011111) << 6) + ($second & 0b00111111);
            if ($ret <= 0x7F) {
                //echo "2 byte UTF8 invalid (less than U+7F)".PHP_EOL;
                return 65533;
            }
            //echo "2 byte UTF8 code point ".chr($first).chr($second)." ".$ret.PHP_EOL;
            return $ret;
        }
        if ($pos + 2 > $len) {
            //echo "3 byte UTF8 invalid (insufficient additional characters)".PHP_EOL;
            return 0;
        }
        if ($first >> 4 == 0b1110) {
            $second = ord($input[$pos]);
            $pos += 1;
            $third = ord($input[$pos]);
            $pos += 1;
            if (($second >> 6) != 0b10) {
                //echo "3 byte UTF8 invalid (invalid second)".PHP_EOL;
                return 65533;
            }
            if (($third >> 6) != 0b10) {
                //echo "3 byte UTF8 invalid (invalid third)".PHP_EOL;
                return 65533;
            }
            $ret = (($first & 0b00001111) << 12) + (($second & 0b00111111) << 6) + ($third & 0b00111111);
            if ($ret <= 0x7FF) {
                //echo "3 byte UTF8 invalid (less than U+7FF)".PHP_EOL;
                return 65533;
            }
            //echo "3 byte UTF8 code point ".chr($first).chr($second).chr($third)." ".$ret.PHP_EOL;
            return $ret;
        }
        if ($pos + 3 > $len) {
            //echo "4 byte UTF8 invalid (insufficient additional characters)".PHP_EOL;
            return 0;
        }
        if ($first >> 3 == 0b11110) {
            $second = ord($input[$pos]);
            $pos += 1;
            $third = ord($input[$pos]);
            $pos += 1;
            $fourth = ord($input[$pos]);
            $pos += 1;
            if (($second >> 6) != 0b10) {
                //echo "4 byte UTF8 invalid (invalid second)".PHP_EOL;
                return 65533;
            }
            if (($third >> 6) != 0b10) {
                //echo "4 byte UTF8 invalid (invalid third)".PHP_EOL;
                return 65533;
            }
            if (($fourth >> 6) != 0b10) {
                //echo "4 byte UTF8 invalid (incorrect fourth)".PHP_EOL;
                return 65533;
            }
            $ret = (($first & 0b00001111) << 18) + (($second & 0b00111111) << 12) + (($third & 0b00111111) << 6) + ($fourth & 0b00111111);
            if ($ret <= 0xFFFF) {
                //echo "4 byte UTF8 invalid (less than U+FFFF)".PHP_EOL;
                return 65533;
            }
            if ($ret > 0x10FFFF) {
                //echo "4 byte UTF8 invalid (greater than U+10FFFF)".PHP_EOL;
                return 65533;
            }
            //echo "4 byte UTF8 code point ".chr($first).chr($second).chr($third).chr($fourth)." ".$ret.PHP_EOL;
            return $ret;
        }
        //echo "? byte UTF-invalid (no valid sync character)".PHP_EOL;
        return 65533;
    }

}