<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Application;
use App\Entity\Slug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class ApplicationSubscriber implements EventSubscriberInterface
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, SerializerInterface $serializer)
    {
        $this->params = $params;
        $this->em = $em;
        $this->serializer = $serializer;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['IngeschrevenpersoonOnBsn', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function IngeschrevenpersoonOnBsn(ViewEvent $event)
    {
        $result = $event->getControllerResult();
        $id = $event->getRequest()->attributes->get('id');
        $slug = $event->getRequest()->attributes->get('slug');
        $contentType = $event->getRequest()->headers->get('accept');
        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
        }
        $method = $event->getRequest()->getMethod();

        // Lats make sure that some one posts correctly
        if (Request::METHOD_GET !== $method || $event->getRequest()->get('_route') != 'api_applications_get_page_on_slug_collection') {
            return;
        }

        // Lets set a return content type
        switch ($contentType) {
            case 'application/json':
                $renderType = 'json';
                break;
            case 'application/ld+json':
                $renderType = 'jsonld';
                break;
            case 'application/hal+json':
                $renderType = 'jsonhal';
                break;
            default:
                $contentType = 'application/json';
                $renderType = 'json';
        }

        $application = $this->em->getRepository(Application::class)->findOneBy(['id' => $id]);
        $slug = $this->em->getRepository(Slug::class)->findOneBy(['application' => $application, 'slug'=>$slug]);
        $result = $slug->getPage();

        // now we need to overide the normal subscriber
        $json = $this->serializer->serialize(
            $result,
            $renderType,
            ['enable_max_depth' => true]
        );

        $response = new Response(
            $json,
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );

        $event->setResponse($response);
    }
}
