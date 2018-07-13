<?php

namespace App\Controller\Dashboard;

use App\Entity\Listing;
use App\Entity\Shipping;
use App\Entity\StaffPick;
use App\Entity\User;
use App\Form\Admin\BanType;
use App\Form\Admin\StaffPickType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class BanController extends Controller
{

    /**
     * @Route("/staff/ban/", name="staffBan")
     */
    public function banAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $banForm = $this->createForm(BanType::class);
        $banForm->handleRequest($request);

        if ($banForm->isSubmitted() && $banForm->isValid()) {
            $userRepo = $em->getRepository(User::class);
            $user = $userRepo->findOneByUsername($banForm->get('username')->getData());

            if (!empty($user)) {
                $user->setBanned(1);
                $em->persist($user);

                if ($user->getRole() === 'vendor') {
                    $em = $this->getDoctrine()->getManager();
                    $listingRepo = $em->getRepository(Listing::class);
                    $listings = $listingRepo->findByUsername($user->getUsername());

                    foreach ($listings as $listing) {
                        $categoryId = $listing->getCategory();
                        $parentId = $listing->getParentCategory();

                        $em->remove($listing);

                        $shippingRepo = $em->getRepository(Shipping::class);
                        $shipping = $shippingRepo->findByListing($listing->getUuid());
                        foreach ($shipping as $single) {
                            $em->remove($single);
                        }

                        if ($listing->getStep() == 7) {
                            $this->get('App\Service\Categories')->removeItem($categoryId);
                            $this->get('App\Service\Categories')->removeItem($parentId);
                        }

                        $this->get('App\Service\ListingImages')->removeImages($listing->getUuid(), $user->getUsername());
                    }
                }
                $em->clear();
                $em->flush();

                $session = new Session();
                $session->getFlashBag()->add('error', 'This user has been banned.');
            } else {
                $session = new Session();
                $session->getFlashBag()->add('error', 'This user does not exist.');
            }

            return $this->redirectToRoute('staffBan');
        }

        return $this->render('/dashboard/admin/ban.html.twig', [
            'banForm' => $banForm->createView(),
        ]);
    }
}
