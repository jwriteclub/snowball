<?php

namespace SnowballPHP;

interface CharSequence {
    /**
     * @param int $index
     * @return string
     */
    function charAt($index);

    /**
     * @return int
     */
    function length();

    /**
     * @param int   $start
     * @param int   $end
     * @return CharSequence
     */
    function subSequence($start, $end);

    /**
     * @return string
     */
    function __toString();
}