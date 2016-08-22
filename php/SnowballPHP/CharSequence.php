<?php

namespace SnowballPHP;

interface CharSequence {
    /**
     * @param int $index
     * @return string
     */
    function charAt($index);

    /**
     * @param int $index
     * @return int
     */
    function intAt($index);

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