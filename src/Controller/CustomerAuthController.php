<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerAddressRepository;
use App\Repository\OrdersRepository;
use App\Repository\CustomerRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CustomerAuthController extends AbstractController
{
    #[Route('/login', name: 'customer_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('customer/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/signup', name: 'customer_signup', methods: ['GET', 'POST'])]
    public function signup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        CustomerRepository $customerRepository,
        EmailVerificationService $emailVerificationService
    ): Response {
        $error = null;
        $formData = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'phoneNumber' => '',
            'shoeSize' => '',
        ];

        // Turbo requires POST requests to respond with a redirect.
        // We keep form errors + previously entered values in flash data.
        if ($request->isMethod('GET')) {
            $flashFormData = $request->getSession()->getFlashBag()->get('signup_form_data');
            if (\count($flashFormData) > 0 && \is_array($flashFormData[0])) {
                $formData = array_merge($formData, $flashFormData[0]);
            }
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('customer_authenticate', $submittedToken)) {
                $error = 'Invalid security token. Please try again.';
            }

            if ($error === null) {
                $title = trim((string) $request->request->get('title', ''));
                $firstName = trim((string) $request->request->get('firstName', ''));
                $lastName = trim((string) $request->request->get('lastName', ''));
                $email = trim((string) $request->request->get('email', ''));
                $password = (string) $request->request->get('password', '');
                $phoneNumber = trim((string) $request->request->get('phoneNumber', ''));
                $shoeSize = trim((string) $request->request->get('shoeSize', ''));

                $formData['firstName'] = $firstName;
                $formData['lastName'] = $lastName;
                $formData['email'] = $email;
                $formData['phoneNumber'] = $phoneNumber;
                $formData['shoeSize'] = $shoeSize;

                // Combine title, firstName, and lastName into fullName
                $fullNameParts = array_filter([$title, $firstName, $lastName]);
                $fullName = implode(' ', $fullNameParts);

                if (empty($firstName) || empty($lastName)) {
                    $error = 'First name and last name are required.';
                }

                if ($error === null && empty($fullName)) {
                    $error = 'Full name is required.';
                }

                $passwordPattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}$/";

                if ($error === null && !preg_match($passwordPattern, $password)) {
                    $error = 'Password must be at least 8 characters and include upper, lower, number, and symbol.';
                }

                if ($error === null) {
                    $customer = new Customer();
                    $customer->setFullName($fullName);
                    $customer->setEmail($email);
                    $customer->setPlainPassword($password);
                    $customer->setRoles(['ROLE_CUSTOMER']);
                    $customer->setIsVerified(false);
                    $customer->setVerificationToken($emailVerificationService->generateVerificationToken());
                    if (!empty($shoeSize)) {
                        $customer->setShoeSize($shoeSize);
                    }
                    if (!empty($phoneNumber)) {
                        // Combine country code with phone number if country code is provided
                        $countryCode = trim((string) $request->request->get('countryCode', ''));
                        if (!empty($countryCode)) {
                            $fullPhoneNumber = $countryCode . ' ' . $phoneNumber;
                        } else {
                            $fullPhoneNumber = $phoneNumber;
                        }
                        $customer->setPhoneNumber($fullPhoneNumber);
                    }
                }

                if ($error === null && empty($email)) {
                    $error = 'Email is required.';
                }

                if ($error === null && empty($password)) {
                    $error = 'Password is required.';
                }

                if ($error === null && $customerRepository->findOneBy(['email' => $email])) {
                    $error = 'An account with this email already exists.';
                }

                if ($error === null) {
                    $violations = $validator->validate($customer, null, ['create']);
                    if (\count($violations) > 0) {
                        $error = $violations[0]->getMessage();
                    }
                }

                if ($error === null) {
                    $hashed = $passwordHasher->hashPassword($customer, $password);
                    $customer->setPassword($hashed);

                    try {
                        $entityManager->persist($customer);
                        $entityManager->flush();

                        $verificationUrl = $this->generateUrl(
                            'app_verify_email',
                            ['token' => $customer->getVerificationToken()],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        );
                        $emailSent = true;
                        try {
                            $emailVerificationService->sendVerificationEmail($customer, $verificationUrl);
                        } catch (\Throwable $e) {
                            $emailSent = false;
                            $this->addFlash(
                                'error',
                                'Account created, but we could not send the verification email right now. Verification link: ' . $verificationUrl
                            );
                        }

                        if ($emailSent) {
                            $this->addFlash('success', 'Account created! We sent a verification email. Please verify your account before logging in.');
                        } else {
                            $this->addFlash('error', 'Tip: check that your Brevo sender/domain is verified, and that your SMTP key is valid.');
                        }

                        return $this->redirectToRoute('customer_login', [], Response::HTTP_SEE_OTHER);
                    } catch (\Throwable $e) {
                        // Show the actual error message for debugging
                        $error = 'Could not create account: ' . $e->getMessage();
                        // For production, you might want to use a generic message:
                        // $error = 'Could not create account. Please try again later.';
                    }
                }
            }

            if ($error !== null) {
                $this->addFlash('error', $error);
                $this->addFlash('signup_form_data', $formData);
            }

            return $this->redirectToRoute('customer_signup', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/signup.html.twig', [
            'error' => $error,
            'formData' => $formData,
        ]);
    }

    #[IsGranted('ROLE_CUSTOMER')]
    #[Route('/account', name: 'customer_account', methods: ['GET', 'POST'])]
    public function account(
        Request $request,
        Security $security,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        CustomerAddressRepository $addressRepository,
        OrdersRepository $ordersRepository,
        ValidatorInterface $validator,
    ): Response {
        $user = $security->getUser();
        if (!$user instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        $allowedTabs = ['personal', 'address', 'orders', 'wishlist', 'notify'];
        $activeTab = (string) $request->query->get('tab', 'personal');
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'personal';
        }

        if ($request->isMethod('POST')) {
            $formType = (string) $request->request->get('form_type', 'personal');
            $activeTab = match ($formType) {
                'address', 'address_delete' => 'address',
                default => 'personal',
            };

            if ($formType === 'address_delete') {
                $this->handleAddressDelete($request, $user, $addressRepository, $entityManager);
            } elseif ($formType === 'address') {
                $this->handleAddressSave($request, $user, $addressRepository, $entityManager, $validator);
            } else {
                $this->handlePersonalProfileSave($request, $user, $customerRepository, $entityManager, $validator);
            }

            return $this->redirectToRoute('customer_account', ['tab' => $activeTab], Response::HTTP_SEE_OTHER);
        }

        $addressForm = null;
        if ($request->query->has('new_address')) {
            $addressForm = $this->buildAddressFormView($user, null);
        } elseif ($request->query->has('edit_address')) {
            $editId = (int) $request->query->get('edit_address');
            $editing = $addressRepository->findOneForCustomer($user, $editId);
            if ($editing instanceof CustomerAddress) {
                $addressForm = $this->buildAddressFormView($user, $editing);
            }
        }

        return $this->render('customer/account.html.twig', [
            'user' => $user,
            'activeTab' => $activeTab,
            'profile' => $this->buildAccountProfileView($user),
            'addresses' => $addressRepository->findForCustomer($user),
            'addressForm' => $addressForm,
            'orders' => $ordersRepository->findForCustomerEmail((string) $user->getEmail()),
        ]);
    }

    private function handlePersonalProfileSave(
        Request $request,
        Customer $user,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): void {
        if (!$this->isCsrfTokenValid('customer_account', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return;
        }

        $title = trim((string) $request->request->get('title', ''));
        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $email = trim((string) $request->request->get('email', ''));
        $countryCode = trim((string) $request->request->get('countryCode', '+63'));
        $phoneLocal = trim((string) $request->request->get('phoneNumber', ''));
        $shoeSize = trim((string) $request->request->get('shoeSize', ''));

        if ($firstName === '' || $lastName === '') {
            $this->addFlash('error', 'First name and last name are required.');
        } elseif ($email === '') {
            $this->addFlash('error', 'Email is required.');
        } else {
            $existing = $customerRepository->findOneByEmailCanonical($email);
            if ($existing instanceof Customer && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'An account with this email already exists.');
            } else {
                $fullNameParts = array_filter([$title, $firstName, $lastName]);
                $user->setFullName(implode(' ', $fullNameParts));
                $user->setEmail($email);
                $user->setShoeSize($shoeSize !== '' ? $shoeSize : null);

                if ($phoneLocal !== '') {
                    $user->setPhoneNumber(trim($countryCode . ' ' . $phoneLocal));
                } else {
                    $user->setPhoneNumber(null);
                }

                $violations = $validator->validate($user);
                if (\count($violations) > 0) {
                    $this->addFlash('error', $violations[0]->getMessage());
                } else {
                    $entityManager->flush();
                    $this->addFlash('success', 'Your profile has been updated.');
                }
            }
        }
    }

    private function handleAddressSave(
        Request $request,
        Customer $user,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): void {
        if (!$this->isCsrfTokenValid('customer_account', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return;
        }

        $addressId = (int) $request->request->get('address_id', 0);
        $address = $addressId > 0
            ? $addressRepository->findOneForCustomer($user, $addressId)
            : null;

        if ($addressId > 0 && !$address instanceof CustomerAddress) {
            $this->addFlash('error', 'Address not found.');

            return;
        }

        $address ??= new CustomerAddress();
        if ($address->getCustomer() === null) {
            $user->addAddress($address);
        }

        $address->setLabel(trim((string) $request->request->get('address_label', 'Home')) ?: 'Home');
        $address->setAddressLine1(trim((string) $request->request->get('address_line1', '')));
        $address->setAddressLine2(trim((string) $request->request->get('address_line2', '')) ?: null);
        $address->setCity(trim((string) $request->request->get('address_city', '')));
        $address->setProvince(trim((string) $request->request->get('address_province', '')) ?: null);
        $address->setPostalCode(trim((string) $request->request->get('address_postal', '')) ?: null);
        $address->setCountry(trim((string) $request->request->get('address_country', 'Philippines')) ?: 'Philippines');
        $address->setContactEmail(trim((string) $request->request->get('address_email', '')) ?: null);
        $address->setContactPhone(trim((string) $request->request->get('address_phone', '')) ?: null);

        $existingAddresses = $addressRepository->findForCustomer($user);
        $makeDefault = $request->request->getBoolean('address_default');
        if ($address->getId() === null && $existingAddresses === []) {
            $makeDefault = true;
        }
        $address->setIsDefault($makeDefault);

        if ($address->getAddressLine1() === '' || $address->getCity() === '') {
            $this->addFlash('error', 'Street address and city are required.');

            return;
        }

        $violations = $validator->validate($address);
        if (\count($violations) > 0) {
            $this->addFlash('error', $violations[0]->getMessage());

            return;
        }

        if ($makeDefault) {
            foreach ($addressRepository->findForCustomer($user) as $other) {
                if ($other->getId() !== $address->getId()) {
                    $other->setIsDefault(false);
                }
            }
        }

        if ($address->getId() === null) {
            $entityManager->persist($address);
        }

        $entityManager->flush();
        $this->addFlash('success', $addressId > 0 ? 'Address updated.' : 'Address added.');
    }

    private function handleAddressDelete(
        Request $request,
        Customer $user,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
    ): void {
        $addressId = (int) $request->request->get('address_id', 0);
        $tokenId = 'customer_address_delete_' . $addressId;

        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return;
        }

        $address = $addressRepository->findOneForCustomer($user, $addressId);
        if (!$address instanceof CustomerAddress) {
            $this->addFlash('error', 'Address not found.');

            return;
        }

        $wasDefault = $address->isDefault();
        $entityManager->remove($address);
        $entityManager->flush();

        if ($wasDefault) {
            $remaining = $addressRepository->findForCustomer($user);
            if ($remaining !== []) {
                $remaining[0]->setIsDefault(true);
                $entityManager->flush();
            }
        }

        $this->addFlash('success', 'Address removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressFormView(Customer $customer, ?CustomerAddress $address): array
    {
        $profile = $this->buildAccountProfileView($customer);
        $defaultPhone = $profile['countryCode'] !== '' && $profile['phoneLocal'] !== ''
            ? trim($profile['countryCode'] . ' ' . $profile['phoneLocal'])
            : $profile['phoneLocal'];

        if ($address instanceof CustomerAddress) {
            return [
                'id' => $address->getId(),
                'label' => $address->getLabel(),
                'addressLine1' => $address->getAddressLine1(),
                'addressLine2' => $address->getAddressLine2() ?? '',
                'city' => $address->getCity(),
                'province' => $address->getProvince() ?? '',
                'postalCode' => $address->getPostalCode() ?? '',
                'country' => $address->getCountry(),
                'contactEmail' => $address->getContactEmail() ?? '',
                'contactPhone' => $address->getContactPhone() ?? '',
                'isDefault' => $address->isDefault(),
            ];
        }

        return [
            'id' => null,
            'label' => 'Home',
            'addressLine1' => '',
            'addressLine2' => '',
            'city' => '',
            'province' => '',
            'postalCode' => '',
            'country' => 'Philippines',
            'contactEmail' => $profile['email'],
            'contactPhone' => $defaultPhone,
            'isDefault' => true,
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   firstName: string,
     *   lastName: string,
     *   email: string,
     *   countryCode: string,
     *   phoneLocal: string,
     *   shoeSize: string,
     *   birthDay: int|null,
     *   birthMonth: int|null,
     *   birthYear: int|null
     * }
     */
    private function buildAccountProfileView(Customer $customer): array
    {
        $fullName = trim((string) $customer->getFullName());
        $title = 'Mr.';
        $firstName = '';
        $lastName = '';

        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $titles = ['Mr.', 'Ms.', 'Mrs.', 'Mx.', 'Dr.'];
            if ($parts !== [] && in_array($parts[0], $titles, true)) {
                $title = $parts[0];
                array_shift($parts);
            }
            if ($parts !== []) {
                $firstName = array_shift($parts) ?? '';
                $lastName = implode(' ', $parts);
            }
        }

        $countryCode = '+63';
        $phoneLocal = '';
        $storedPhone = trim((string) $customer->getPhoneNumber());
        if ($storedPhone !== '' && preg_match('/^(\+\d{1,4})\s*(.*)$/', $storedPhone, $matches)) {
            $countryCode = $matches[1];
            $phoneLocal = trim($matches[2]);
        } elseif ($storedPhone !== '') {
            $phoneLocal = $storedPhone;
        }

        return [
            'title' => $title,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => (string) $customer->getEmail(),
            'countryCode' => $countryCode,
            'phoneLocal' => $phoneLocal,
            'shoeSize' => (string) ($customer->getShoeSize() ?? ''),
            'birthDay' => null,
            'birthMonth' => null,
            'birthYear' => null,
        ];
    }

    #[Route('/logout', name: 'customer_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by Symfony security.');
    }
}

