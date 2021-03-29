<?php

namespace OuterEdge\BulkActions\Ui\Component\Action;

class AddParams extends \Magento\Ui\Component\Action
{
    protected $urlBuilder;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $components = array(),
        array $data = array(),
        $actions = null
    ) {
        parent::__construct($context, $components, $data, $actions);

        $this->urlBuilder = $urlBuilder;
    }

    public function prepare()
    {
        parent::prepare();

        $config = $this->getConfiguration();

        $params = array('notify' => '1');

        $config['url'] = $this->urlBuilder->getUrl('shipbulkactions/order/massordership/', $params);

        $this->setData('config', $config);
    }
}
