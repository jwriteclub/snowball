<?php

namespace SnowballPHP;

abstract class SnowballProgram {

    // current string
    /** @var StringBuffer */
    protected  $current;
    /** @var int */
    protected $cursor;
    /** @var int */
    protected $limit;
    /** @var int */
    protected $limit_backward;
    /** @var int */
    protected $bra;
    /** @var int */
    protected $ket;

    function __construct() {
        $cname = $this->sbClass();
        $this->current = new $cname();
        $this->setCurrent("");
    }

    /**
     * Set the current string.
     * @var $value string|StringBuffer
     */
    public function setCurrent($value) {
        $this->current->replace(0, $this->current->length(), $value);
        $this->cursor = 0;
        $this->limit = $this->current->length();
        $this->limit_backward = 0;
        $this->bra = $this->cursor;
        $this->ket = $this->limit;
    }

    /**
     * Get the current string.
     * @return string
     */
    public function getCurrent() {
        $result = $this->current->__toString();
        // Make a new StringBuffer.  If we reuse the old one, and a user of
        // the library keeps a reference to the buffer returned (for example,
        // by converting it to a String in a way which doesn't force a copy),
        // the buffer size will not decrease, and we will risk wasting a large
        // amount of memory.
        // Thanks to Wolfram Esser for spotting this problem.
        $cname = $this->sbClass();
        $this->current = new $cname();
        return $result;
    }

    /**
     * @param $other SnowballProgram
     */
    protected function copy_from($other) {
        $this->current          = $other->current;
        $this->cursor           = $other->cursor;
        $this->limit            = $other->limit;
        $this->limit_backward   = $other->limit_backward;
        $this->bra              = $other->bra;
        $this->ket              = $other->ket;
    }

    /**
     * @param string    $s      Converted from char[]
     * @param int       $min
     * @param int       $max
     * @return bool
     */
    protected function in_grouping($s, $min, $max) {
        if ($this->cursor >= $this->limit) return false;
        $ch = $this->current->intAt($this->cursor);
	    if ($ch > $max || $ch < $min) return false;
	    $ch -= $min;
	    if (($s[$ch >> 3] & (0x1 << ($ch & 0x7))) == 0) return false;
	    $this->cursor++;
	    return true;
    }

    /**
     * @param string    $s
     * @param int       $min
     * @param int       $max
     * @return bool
     */
    protected function in_grouping_b($s, $min, $max) {
        if ($this->cursor <= $this->limit_backward) return false;
        $ch = $this->current->intAt($this->cursor - 1);
	    if ($ch > $max || $ch < $min) return false;
	    $ch -= $min;
	    if (($s[$ch >> 3] & (0x1 << ($ch & 0x7))) == 0) return false;
	    $this->cursor--;
	    return true;
    }

    /**
     * @param string    $s
     * @param int       $min
     * @param int       $max
     * @return bool
     */
    protected function out_grouping($s, $min, $max) {
        if ($this->cursor >= $this->limit) return false;
        $ch = $this->current->intAt($this->cursor);
	    if ($ch > $max || $ch < $min) {
            $this->cursor += 1;
            return true;
        }
	    $ch -= $min;
	    if (($s[$ch >> 3] & (0x1 << ($ch & 0x7))) == 0) {
            $this->cursor += 1;
            return true;
        }
	    return false;
    }

    /**
     * @param string    $s
     * @param int       $min
     * @param int       $max
     * @return bool
     */
    protected function out_grouping_b($s, $min, $max) {
        if ($this->cursor <= $this->limit_backward) return false;
        $ch = $this->current->intAt($this->cursor - 1);
	    if ($ch > $max || $ch < $min) {
            $this->cursor -= 1;
            return true;
        }
	    $ch -= $min;
	    if (($s[$ch >> 3] & (0x1 << ($ch & 0x7))) == 0) {
            $this->cursor -= 1;
            return true;
        }
	    return false;
    }

    /**
     * @param CharSequence|string $s
     * @return bool
     */
    protected function eq_s($s) {
        if (!($s instanceof CharSequence)) {
            $cname = $this->sbClass();
            $s = new $cname($s);
        }
        if ($this->limit - $this->cursor < $s->length()) return false;
	    for ($i = 0; $i != $s->length(); $i += 1) {
            if ($this->current->intAt($this->cursor + $i) != $s->intAt($i)) return false;
        }
	    $this->cursor += $s->length();
	    return true;
    }

    /**
     * @param CharSequence|string   $s
     * @return bool
     */
    protected function eq_s_b($s) {
        if (!($s instanceof CharSequence)) {
            $cname = $this->sbClass();
            $s = new $cname($s);
        }
        if ($this->cursor - $this->limit_backward < $s->length()) return false;
	    for ($i = 0; $i != $s->length(); $i += 1) {
            if ($this->current->intAt($this->cursor - $s->length() + $i) != $s->intAt($i)) return false;
        }
	    $this->cursor -= $s->length();
	    return true;
    }

    /**
     * @param Among[] $v
     * @return int
     * @throws \Exception
     */
    protected function find_among($v) {
        $i = 0;
	    $j = count($v);

        $c = $this->cursor;
        $l = $this->limit;

	    $common_i = 0;
	    $common_j = 0;

	    $first_key_inspected = false;

	    while(true) {
            $k = $i + (($j - $i) >> 1);
	        $diff = 0;
	        $common = $common_i < $common_j ? $common_i : $common_j; // smaller
	        $w = $v[$k];
	        for ($i2 = $common; $i2 < $w->s->length(); $i2 += 1) {
                if ($c + $common == $l) {
                    $diff = -1;
                    break;
                }
                $diff = $this->current->intAt($c + $common) - $w->s->intAt($i2);
		        if ($diff != 0) break;
		        $common++;
	        }
	        if ($diff < 0) {
                $j = $k;
                $common_j = $common;
            } else {
                $i = $k;
                $common_i = $common;
            }
	        if ($j - $i <= 1) {
                if ($i > 0) break; // v->s has been inspected
                if ($j == $i) break; // only one item in v

                // - but now we need to go round once more to get
                // v->s inspected. This looks messy, but is actually
                // the optimal approach.

                if ($first_key_inspected) break;
                $first_key_inspected = true;
            }
	    }
        while(true) {
            $w = $v[$i];
            if ($common_i >= $w->s->length()) {
                //$cursor = $c + $w->s->length(); // TODO: Unused?
                if ($w->method == null) return $w->result;
                //$res = false;
                try {
                    $resobj = $w->method->invoke($this);
                    $res = $resobj->__toString() == "true";
                } catch (\Exception $e) {
                    $res = false;
                    // FIXME - debug message
                }
                //$cursor = $c + $w->s->length();
                if ($res) return $w->result;
            }
            $i = $w->substring_i;
            if ($i < 0) return 0;
        }
        throw new \Exception("Unexpected execution");
    }

    /**
     * find_among_b is for backwards processing. Same comments apply
     * @param Among[] $v
     * @return int
     * @throws \Exception
     */
    protected function find_among_b($v) {
        $i = 0;
	    $j = count($v);

	    $c = $this->cursor;
        $lb = $this->limit_backward;

	    $common_i = 0;
	    $common_j = 0;

	    $first_key_inspected = false;

	    while(true) {
            $k = $i + (($j - $i) >> 1);
	        $diff = 0;
	        $common = $common_i < $common_j ? $common_i : $common_j;
	        $w = $v[$k];
	        for ($i2 = $w->s->length() - 1 - $common; $i2 >= 0; $i2--) {
                if ($c - $common == $lb) {
                    $diff = -1;
                    break;
                }
                $diff = $this->current->intAt($c - 1 - $common) - $w->s->intAt($i2);
                if ($diff != 0) break;
                $common++;
            }
            if ($diff < 0) {
                $j = $k;
                $common_j = $common;
            } else {
                $i = $k;
                $common_i = $common;
            }
            if ($j - $i <= 1) {
                if ($i > 0) break;
                if ($j == $i) break;
                if ($first_key_inspected) break;
                $first_key_inspected = true;
            }
        }
	    while(true) {
            $w = $v[$i];
	        if ($common_i >= $w->s->length()) {
                $this->cursor = $c - $w->s->length();
                if ($w->method == null) return $w->result;
                try {
                    $resobj = $w->method->invoke($this);
                    $res = $resobj->__toString() == "true";
                } catch (\Exception $e) {
                    $res = false;
                    // FIXME - debug message
                }
		        $this->cursor = $c - $w->s->length();
		        if ($res) return $w->result;
	        }
	        $i = $w->substring_i;
	        if ($i < 0) return 0;
	    }
        throw new \Exception("Unexpected execution");
    }

    /**
     * To replace chars between c_bra and c_ket in current by the
     * chars in s.
     * @param int                   $c_bra
     * @param int                   $c_ket
     * @param CharSequence|string   $s
     * @return int
     */
    protected function replace_s($c_bra, $c_ket, $s) {
        if (!($s instanceof CharSequence)) {
            $cname = $this->sbClass();
            $s = new $cname($s);
        }
        $adjustment = $s->length() - ($c_ket - $c_bra);
	    $this->current->replace($c_bra, $c_ket, $s);
	    $this->limit += $adjustment;
	    if ($this->cursor >= $c_ket) $this->cursor += $adjustment;
        else if ($this->cursor > $c_bra) $this->cursor = $c_bra;
	    return $adjustment;
    }

    /**
     * @return void
     */
    protected function slice_check() {
        if ($this->bra < 0 ||
            $this->bra > $this->ket ||
            $this->ket > $this->limit ||
            $this->limit > $this->current->length())   // this line could be removed
        {
            // FIXME: report error somehow.
            fprintf(STDERR, "faulty slice operation: -1 0");
            // TODO: Throw new \Exception?
            /*
                fprintf(stderr, "faulty slice operation:\n");
                debug(z, -1, 0);
                exit(1);
                */
        }
    }

    /**
     * @param CharSequence|string  $s
     * @return void
     */
    protected function slice_from($s) {
        if (!($s instanceof CharSequence)) {
            $cname = $this->sbClass();
            $s = new $cname($s);
        }
        $this->slice_check();
        $this->replace_s($this->bra, $this->ket, $s);
    }

    /**
     * @return void
     */
    protected function slice_del() {
        $this->slice_from("");
    }

    /**
     * @param int       $c_bra
     * @param int       $c_ket
     * @param CharSequence|string   $s
     * @return void
     */
    protected function insert($c_bra, $c_ket, $s) {
        if (!($s instanceof CharSequence)) {
            $cname = $this->sbClass();
            $s = new $cname($s);
        }
        $adjustment = $this->replace_s($c_bra, $c_ket, $s);
	    if ($c_bra <= $this->bra) $this->bra += $adjustment;
	    if ($c_bra <= $this->ket) $this->ket += $adjustment;
    }

    /**
     * Copy the slice into the supplied StringBuffer
     * @param StringBuffer  $s
     * @return StringBuffer
     */
    protected function slice_to($s) {
        $this->slice_check();
        //$len = $this->ket - $this->bra;
	    $s->replace(0, $s->length(), $this->current->substring($this->bra, $this->ket));
	    return $s;
    }

    /**
     * @param StringBuffer  $s
     * @return StringBuffer
     */
    protected function assign_to($s) {
        $s->replace(0, $s->length(), $this->current->substring(0, $this->limit));
        return $s;
    }

    protected abstract function sbClass();
}