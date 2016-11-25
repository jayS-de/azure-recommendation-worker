<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use Symfony\Component\Console\Input\InputInterface;

class ConfigLoader
{
    private $optionName;
    private $envName;

    public function __construct($optionName, $envName)
    {
        $this->optionName = $optionName;
        $this->envName = $envName;
    }
    public function load(InputInterface $input)
    {
        $fileName = $input->getOption($this->optionName);

        if (getenv($this->envName)) $fileName = getenv($this->envName);

        if (is_file($fileName)) {
            return json_decode(file_get_contents($fileName), true);
        }

        return [];
    }
}
