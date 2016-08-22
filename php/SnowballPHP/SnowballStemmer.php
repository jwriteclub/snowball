<?php

namespace SnowballPHP;

abstract class SnowballStemmer extends SnowballProgram {
    /** @return bool */
    abstract function stem();
}