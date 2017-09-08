<?php
namespace framework\driver\db\builder;

class Oracle extends Builder
{
    const ORDER_RANDOM = 'DBMS_RANDOM.value';
    const KEYWORD_ESCAPE_LEFT = '"';
    const KEYWORD_ESCAPE_RIGHT = '"';
    
    public static function limitClause($limit)
    {
        if (is_array($limit)) {
            return " OFFSET ".$limit[0]." ROWS FETCH NEXT ".$limit[1].' ROWS ONLY';
        } else {
            return " FETCH FIRST $limit ROWS ONLY";
        }
    }
}