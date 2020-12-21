<?php
namespace App\Shell;

use Cake\Console\Shell;

Class HelloShell extends Shell
{
    public function main()
    {
        $this->out('Hai Dunia Gembiralah');
    }
}
