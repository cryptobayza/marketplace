<?php

namespace App\Controller\Purchase;

use App\Entity\Listing;
use App\Entity\Orders;
use App\Entity\ShippingOption;
use App\Form\BuyType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class BuyController extends Controller
{

    /**
     * @Route("/buy/{uuid}/", name="buy")
     */
    public function orderAction(Request $request, $uuid)
    {
        $em = $this->getDoctrine()->getManager();

        $listingRepo = $em->getRepository(Listing::class);
        $listing = $listingRepo->findOneByUuid($uuid);

        if ($listing->getFlag() == true || ($listing->getStock() == 0 && !is_null($listing->getStock()))) {
            return $this->render('/buy.html.twig', [
                'order' => [
                    'flag' => true,
                    'title' => $listing->getTitle(),
                ],
            ]);
        }

        $buyForm = $this->createForm(BuyType::class, [], [
            'placeholder' => 'Please enter additional information (for example your shipping information). If you are ordering a digital product, follow the instructions on the product page, a mailing address is not necessary.',
        ]);
        $buyForm->handleRequest($request);

        $vendorProfile = $this->get('App\Service\Profile')->getProfile($listing->getUsername());

        $shippingOptionRepo = $em->getRepository(ShippingOption::class);
        $shipping = $shippingOptionRepo->findOneById($request->query->get('shipping'));

        //check if integer
        $quantity = 1;
        if (filter_var($request->query->get('quantity'), FILTER_VALIDATE_INT)) {
            $quantity = $request->query->get('quantity');
        }

        $mktFee = $this->get('App\Service\Config')->getFee($vendorProfile->getLevel())*(($listing->getPrice() - $listing->getDiscount()) * $quantity);

        //set max fee
        if ($mktFee > $this->get('App\Service\Config')->getMaxFee($vendorProfile->getLevel())) {
            $mktFee = $this->get('App\Service\Config')->getMaxFee($vendorProfile->getLevel());
        }

        $prices = $this->get('App\Service\Currency');

        $wallet = $this->get('App\Service\Wallet\WalletFactory');
        switch ($prices = $this->get('App\Service\Currency')) {
            case 'xmr':
                $cryptoCurrency = 'xmr';
                break;
            case 'zec':
                $cryptoCurrency = 'zec';
                break;
            default:
                $cryptoCurrency = 'btc';
                $bitcoin = $wallet->create('bitcoin');
                $blockchainFee = number_format(((float)($bitcoin->getfeerate()/100000000)/1.5) * $prices->getFiatBTC()[$listing->getFiat()], 2, '.', '');
                break;
        }

        //discount plus fee
        $discount = number_format((($listing->getDiscount() * $quantity) + ($listing->getDiscount() * $quantity * $mktFee)), 2, '.', '');
        $price = number_format((($listing->getPrice() - $listing->getDiscount()) * $quantity)+$mktFee, 2, '.', '');
        $totalPrice = number_format(($price)+($blockchainFee)+$shipping->getPrice(), 2, '.', '');
        $cryptoTotal = number_format($totalPrice * $prices->getBTC()[$listing->getFiat()], 8, '.', '');

        //get profile
        $profile = $this->get('App\Service\Profile')->getProfile();

        $multisig = '2-of-2';
        if ($profile->getBTCPublic() != null) {
            $multisig = '2-of-3';
        }

        /**
         * adds $cryptoCurrency which is displayed to user in table
         * calculates prices based on what cryptocurrency
         **/

        if ($buyForm->isSubmitted() && $buyForm->isValid() && ($listing->getStock() > 0 || is_null($listing->getStock()))) {
            $address = null;

            switch ($cryptoCurrency) {
                case 'btc':
                    $cryptoCurrency = 'btc';
                    $cryptoPrice = $price * $prices->getBTC()[$listing->getFiat()];
                    $shippingCryptoPrice = $shipping->getPrice() * $prices->getBTC()[$listing->getFiat()];
                    $cryptoTotal = $totalPrice * $prices->getBTC()[$listing->getFiat()];
                    $cryptoFee = $mktFee * $prices->getBTC()[$listing->getFiat()];

                    $blockchainFee = number_format((float)($bitcoin->getfeerate()/100000000)/1.5, 8, '.', '');
                    $marketaddr = $bitcoin->createnewaddress();
                    $marketpub = $bitcoin->getpubkeys($marketaddr)[0];
                    $blockchainFee = number_format((float)($bitcoin->getfeerate()/100000000)/1.5, 8, '.', '');
                    $cryptoTotal += $blockchainFee;
                    if (empty($marketpub)) {
                        die("Error generating public key.");
                    }
                    if ($multisig == '2-of-2') {
                        $walletDerive = $this->get('App\Service\Wallet\WalletDerive');
                        $data = $bitcoin->createmultisig(2, [$walletDerive->derive_keys($vendorProfile->getBTCPublic(), $vendorProfile->getHDLevel()), $marketpub]);
                        $address = $data['address'];
                        $redeem = $data['redeemScript'];
                    } else {
                        $walletDerive = $this->get('App\Service\Wallet\WalletDerive');
                        $data = $bitcoin->createmultisig(2, [$profile->getBTCPublic(), $marketpub, $walletDerive->derive_keys($vendorProfile->getBTCPublic(), $vendorProfile->getHDLevel())]);
                        $address = $data['address'];
                        $redeem = $data['redeemScript'];
                    }
                    $vendorProfile->setHDLevel($vendorProfile->getHDLevel() + 1);
                    $em->persist($vendorProfile);
                    $em->flush();
                    $em->clear();
                    break;
                case 'xmr':
                    $cryptoCurrency = 'xmr';
                    $cryptoPrice = $price * $prices->getXMR()[$listing->getFiat()];
                    $shippingCryptoPrice = $shipping->getPrice() * $prices->getXMR()[$listing->getFiat()];
                    $cryptoTotal = $totalPrice * $prices->getXMR()[$listing->getFiat()];
                    $cryptoFee = $mktFee * $prices->getXMR()[$listing->getFiat()];
                    $multisig = 'None';
                    break;
                case 'zec':
                    $cryptoCurrency = 'zec';
                    $cryptoPrice = $price * $prices->getZEC()[$listing->getFiat()];
                    $shippingCryptoPrice = $shipping->getPrice() * $prices->getZEC()[$listing->getFiat()];
                    $cryptoTotal = $totalPrice * $prices->getZEC()[$listing->getFiat()];
                    $cryptoFee = $mktFee * $prices->getZEC()[$listing->getFiat()];
                    $multisig = 'None';
                    break;
            }

            if (is_null($address)) {
                die("Address is null");
            }

            $uuid = \bin2hex(openssl_random_pseudo_bytes(16));

            $message = $buyForm->get('message')->getData();
            if ($buyForm->get('encrypt')->getData() == 'On') {
                $gpg = new \gnupg();
                $gpg->addencryptkey($vendorProfile->getFingerprint());
                $message = $gpg->encrypt($message);
            }

            $order = new Orders();
            if ($cryptoCurrency == 'btc') {
                $order->setRedeem($redeem);
            }

            $order->setUUID($uuid);
            $order->setFee($mktFee);
            $order->setCryptoFee($cryptoFee);
            $order->setBlockchainFee($blockchainFee);
            $order->setAmount($quantity);
            $order->setListing($listing->getUuid());
            $order->setStatus('waiting');
            $order->setBootstrap('warning');
            $order->setCrypto($cryptoCurrency);
            $order->setShippingType($listing->getType());
            $order->setTitle($listing->getTitle());
            $order->setShippingPrice($shipping->getPrice());
            $order->setShippingOption($shipping->getShippingOption());
            $order->setAddress($address);
            $order->setPrice($price);
            $order->setTotal($totalPrice);
            $order->setShippingCryptoPrice($shippingCryptoPrice);
            $order->setCryptoPrice($cryptoPrice);
            $order->setCryptoTotal($cryptoTotal);
            $order->setStartDate(time());
            $order->setVendor($listing->getUsername());
            $order->setBuyer($this->getUser()->getUsername());
            $order->setMultisig($multisig);
            $order->setFiat($listing->getFiat());
            $em->persist($order);
            $em->flush();
            $em->clear();

            $msgGen = $this->get('App\Service\Mailer');
            $msgGen->setThread(substr($order->getTitle(), 0, 32), $order->getVendor(), $uuid, 1);
            $msgGen->setThread(substr($order->getTitle(), 0, 32), $order->getBuyer(), $uuid, 1);
            $msgGen->setMessage($message, $uuid);

            $listingRepo = $em->getRepository(Listing::class);
            $listing = $listingRepo->findOneByUuid($order->getListing());
            if (!is_null($listing->getStock())) {
                $listing->setStock($listing->getStock() - 1);
            }
            $em->persist($listing);
            $em->flush();
            $em->clear();

            return $this->redirect('/pay/' . $order->getUUID() . '/');
        }


        return $this->render('/buy.html.twig', [
            'pgp' => $vendorProfile->getPgp(),
            'order' => [
                'flag' => false,
                'amount' => $quantity,
                'item' => $listing->getId(),
                'title' => $listing->getTitle(),
                'vendor' => $vendorProfile,
                'price' => $price,
                'shipping_price' => $shipping->getPrice(),
                'discount' => $discount,
                'blockchain_fee' => $blockchainFee,
                'total' => $totalPrice,
                'crypto_currency' => $cryptoCurrency,
                'crypto_total' => $cryptoTotal,
                'shipping' => $listing->getType(),
                'delivery' => $shipping->getShippingOption(),
                'fiat' => $listing->getFiat(),
            ],
            'error' => '',
            'messageForm' => $buyForm->createView(),
            'multisig' => $multisig,
        ]);
    }
}
