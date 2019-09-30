<?php


namespace Eres\SyliusReferralMarketingPlugin\Controller;

use Eres\SyliusReferralMarketingPlugin\Entity\Reference;
use Eres\SyliusReferralMarketingPlugin\Event\ReferenceEvent;
use Eres\SyliusReferralMarketingPlugin\Form\Type\ReferenceType;
use Eres\SyliusReferralMarketingPlugin\Service\TransparentPixelResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class ReferenceController extends AbstractController
{
    public function indexAction(): Response
    {
        $references = $this->getDoctrine()->getRepository(Reference::class)->findBy([
            'invitee' => $this->getUser()->getCustomer()
        ]);

        return $this->render('@EresSyliusReferralMarketingPlugin/shop/index.html.twig', [
            'references' => $references
        ]);
    }

    public function newAction(Request $request): Response
    {
        $referance = new Reference();
        $referance->setInvitee($this->getUser()->getCustomer());

        $form = $this->createForm(ReferenceType::class, $referance);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $referance = $form->getData();

            $promotionService = $this->get('eres_sylius_referral_marketing_plugin.promotion');

            $referance->setHash($promotionService->createHash($this->getUser()->getCustomer()->getEmail(), $referance->getReferrerEmail()));
            $em = $this->getDoctrine()->getManager();
            $em->persist($referance);
            $em->flush();

            $this->get('sylius.email_sender')->send('reference_invite', [$referance->getReferrerEmail()], [
                'name' => $referance->getReferrerName(),
                'email' => $referance->getReferrerEmail(),
                'hash' => $referance->getHash()
            ]);

            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('success', 'sylius.customer.add_address');

            return $this->redirectToRoute('eres_sylius_referral_marketing_index');
        }

        return $this->render('@EresSyliusReferralMarketingPlugin/shop/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    public function checkAction($hash, $_format)
    {
        $referrer = $this->getDoctrine()->getRepository(Reference::class)->findOneBy([
            'hash' => $hash,
            'status' => false
        ]);

        if ($referrer) {
            $referrer->setStatus(true);
            $this->getDoctrine()->getManager()->flush();

            $event = new ReferenceEvent($referrer);
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch($event, ReferenceEvent::NAME);

        }

        return new TransparentPixelResponse($_format);

    }
}
