<?php

namespace OuterEdge\BulkActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Sales\Model\Order as ModelOrder;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Framework\Message\ManagerInterface;
use Magento\Shipping\Model\ShipmentNotifier;

class MassOrderShip extends \Magento\Backend\App\Action
{
    /**
     * @var ModelOrder
     */
    protected $orderModel;

    /**
     * @var ConvertOrder
     */
    protected $convertOrder;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * Preparation constructor.
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param ModelOrder $orderModel
     * @param ConvertOrder $convertOrder
     * @param ShipmentNotifier $shipmentNotifier
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        ModelOrder $orderModel,
        ConvertOrder $convertOrder,
        ShipmentNotifier $shipmentNotifier
    ) {
        parent::__construct($context);

        $this->messageManager = $messageManager;
        $this->orderModel = $orderModel;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $ordersId = $this->getRequest()->getParam('selected');
        $notify = $this->getRequest()->getParam('notify');

        if (!empty($ordersId)) {
            // Ship Order
            foreach ($ordersId as $orderId) {

                $order = $this->orderModel->loadByAttribute('entity_id', $orderId);
                if ($order->canShip()) {

                    $convertOrder = $this->convertOrder;
                    $shipment = $convertOrder->toShipment($order);

                    foreach ($order->getAllItems() AS $orderItem) {
                        // Check if order item has qty to ship or is virtual
                        if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }
                        $qtyShipped = $orderItem->getQtyToShip();
                        // Create shipment item with qty
                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                        // Add shipment item to shipment
                        $shipment->addItem($shipmentItem);
                    }

                    // Register shipment
                    $shipment->register();
                    $shipment->getOrder()->setIsInProcess(true);

                    try {
                        // Save created shipment and order
                        $shipment->save();
                        $shipment->getOrder()->save();

                        // Send email
                        if ($notify) {
                            $this->shipmentNotifier->notify($shipment);
                            $shipment->save();
                        }
                        $this->messageManager->addSuccess(__("Shipment Succesfully Generated for order: #".$order->getIncrementId()));
                    } catch (\Exception $e) {
                        $this->messageManager->addError(__('Cannot ship order'. $e->getMessage()));
                    }

                } else {
                    $this->messageManager->addError(__("Cannot ship order, becuase It's already created or something went wrong"));
                }
            }
        }
        return $resultRedirect->setPath('sales/order/index', [], ['error' => true]);
    }
}
