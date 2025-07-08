<?php

namespace __NAMESPACE__;

use SplitPHP\DbManager\Seed;
use SplitPHP\Database\DbVocab;

class __CLASSNAME__ extends Seed{
  public function apply(){
    /**
     * Here goes your seed's statements. For example, the following code
     * populates a table called 'Person', with 100 rows, passing along the desired values and patterns
     * in each field:
     * 
     * $this->SeedTable('Person', batchSize: 100)
     *   ->onField('uuid', true)->setByFunction(function() { // The 'true' here is a flag that indicates it's a key field, which will be used to generate the seed's reversion.
     *     $data = random_bytes(16);
     *     
     *     // Versão 4 (bits 12–15 do byte 7)
     *     $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
     *     // Variante (bits 6–7 do byte 9)
     *     $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
     *  
     *     return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
     *   })
     * ->onField('name')->setRandomStr(1, 100)
     * ->onField('birthday')->setRandomDate()
     * ->onField('active')->setFixedValue(1);
     */
  }
}