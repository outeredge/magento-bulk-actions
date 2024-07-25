<?php

namespace OuterEdge\BulkActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class MassOrderStatus extends \Magento\Backend\App\Action
{
    public function __construct(
        Context $context,
        protected OrderRepositoryInterface $orderRepository,
        protected LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ordersId = $this->getRequest()->getParam('selected');

        if (!empty($ordersId)) {
            foreach ($ordersId as $orderId) {

                $order = $this->orderRepository->get($orderId);
                $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);

                try {
                    $this->orderRepository->save($order);
                } catch (\Exception $e) {
                    $this->logger->error($e);
                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                }
            }
        }
        return $resultRedirect->setPath('sales/order/index', [], ['error' => true]);
    }
}
