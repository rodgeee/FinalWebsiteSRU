<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Orders;
use App\Form\CustomerCheckoutType;
use App\Repository\CustomerAddressRepository;
use App\Repository\OrdersRepository;
use App\Service\Cart\CartService;
use App\Service\Checkout\CustomerCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/checkout', name: 'customer_checkout_')]
final class CheckoutController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CartService $cart,
        CustomerCheckoutService $checkoutService,
        CustomerAddressRepository $addressRepository,
    ): Response {
        if ($cart->isEmpty()) {
            $this->addFlash('error', 'Your bag is empty. Add items before checkout.');

            return $this->redirectToRoute('public_releases');
        }

        $customer = $this->getCustomer();
        $addresses = $addressRepository->findForCustomer($customer);

        if ($addresses === []) {
            $this->addFlash('error', 'Please add a shipping address in your account before checkout.');

            return $this->redirectToRoute('customer_account', ['tab' => 'address', 'new_address' => 1]);
        }

        $form = $this->createForm(CustomerCheckoutType::class, null, [
            'addresses' => $addresses,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isCsrfTokenValid('checkout_place', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid security token.');
            }

            $data = $form->getData();
            $paymentMethod = (string) ($data['paymentMethod'] ?? '');
            $orderNotes = isset($data['orderNotes']) ? (string) $data['orderNotes'] : null;
            $addressId = (int) ($data['addressId'] ?? 0);

            $shippingAddress = $this->resolveAddress($addresses, $addressId);
            if (!$shippingAddress instanceof CustomerAddress) {
                $this->addFlash('error', 'Please select a valid shipping address.');

                return $this->redirectToRoute('customer_checkout_index');
            }

            $result = $checkoutService->placeOrder($customer, $paymentMethod, $shippingAddress, $orderNotes);
            if ($result['ok'] === false) {
                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('customer_checkout_index');
            }

            /** @var Orders $order */
            $order = $result['order'];
            $this->addFlash('success', 'Order placed successfully. Thank you!');

            return $this->redirectToRoute('customer_checkout_success', ['id' => $order->getId()]);
        }

        return $this->render('public/checkout.html.twig', [
            'form' => $form,
            'lines' => $cart->getLines(),
            'subtotal' => $cart->getSubtotal(),
            'itemCount' => $cart->getItemCount(),
            'addresses' => $addresses,
        ]);
    }

    #[Route('/success/{id}', name: 'success', methods: ['GET'])]
    public function success(int $id, OrdersRepository $ordersRepository): Response
    {
        $customer = $this->getCustomer();
        $order = $ordersRepository->findOneBy([
            'id' => $id,
            'Email' => $customer->getEmail(),
        ]);

        if (!$order instanceof Orders) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('public/checkout_success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * @param list<CustomerAddress> $addresses
     */
    private function resolveAddress(array $addresses, int $addressId): ?CustomerAddress
    {
        foreach ($addresses as $address) {
            if ((int) $address->getId() === $addressId) {
                return $address;
            }
        }

        return null;
    }

    private function getCustomer(): Customer
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
