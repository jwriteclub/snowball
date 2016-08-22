<?php
/*
 * This file is part of the snowball project, licensed under
 * the BSD open source license, which should have been included
 * along with this code, or may be accessed at the project's website
 * at https://bitbucket.org/jwriteclub/redis-backup
 *
 * Copyright (c) 2016 SendFaster, Inc.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, 
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * Contact: info@sendfaster.com
 *
 */

namespace SnowballPHP;

class StringBufferUtf8 extends StringBuffer {

    public function __construct($arg = null) {
        parent::__construct($arg);
    }

    protected function encodeChar($int) {
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
        return $this->encodeChar(65533);
    }

    protected function decodeChar($input, &$pos = 0) {
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