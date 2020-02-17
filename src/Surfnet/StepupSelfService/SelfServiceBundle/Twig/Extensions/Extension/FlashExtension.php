<?php

namespace Surfnet\StepupSelfService\SelfServiceBundle\Twig\Extensions\Extension;

/**
 * MopaBootstrap Flash Extension.
 *
 * @author Nikolai Zujev (jaymecd) <nikolai.zujev@gmail.com>
 */
class FlashExtension extends \Twig_Extension
{
    /**
     * @var array
     */
    protected $mapping = [];

    /**
     * Constructor.
     *
     * @param array $mapping
     */
    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('mopa_bootstrap_flash_mapping', [$this, 'getMapping'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Get flash mapping.
     *
     * @return array
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mopa_bootstrap_flash';
    }
}
