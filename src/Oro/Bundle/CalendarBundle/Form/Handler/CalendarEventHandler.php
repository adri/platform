<?php

namespace Oro\Bundle\CalendarBundle\Form\Handler;

use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

use Oro\Bundle\ActivityBundle\Manager\ActivityManager;
use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Bundle\CalendarBundle\Entity\Calendar;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\SecurityBundle\SecurityFacade;

class CalendarEventHandler
{
    /** @var FormInterface */
    protected $form;

    /** @var Request */
    protected $request;

    /** @var ObjectManager */
    protected $manager;

    /** @var ActivityManager */
    protected $activityManager;

    /** @var EntityRoutingHelper */
    protected $entityRoutingHelper;

    /** @var SecurityFacade */
    protected $securityFacade;

    /**
     * @param FormInterface       $form
     * @param Request             $request
     * @param ObjectManager       $manager
     * @param ActivityManager     $activityManager
     * @param EntityRoutingHelper $entityRoutingHelper
     * @param SecurityFacade      $securityFacade
     */
    public function __construct(
        FormInterface $form,
        Request $request,
        ObjectManager $manager,
        ActivityManager $activityManager,
        EntityRoutingHelper $entityRoutingHelper,
        SecurityFacade $securityFacade
    ) {
        $this->form                = $form;
        $this->request             = $request;
        $this->manager             = $manager;
        $this->activityManager     = $activityManager;
        $this->entityRoutingHelper = $entityRoutingHelper;
        $this->securityFacade      = $securityFacade;
    }

    /**
     * Get form, that build into handler, via handler service
     *
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Process form
     *
     * @param  CalendarEvent $entity
     * @throws \LogicException
     *
     * @return bool  True on successful processing, false otherwise
     */
    public function process(CalendarEvent $entity)
    {
        if (!$entity->getCalendar()) {
            if ($this->securityFacade->getLoggedUser() && $this->securityFacade->getOrganization()) {
                /** @var Calendar $defaultCalendar */
                $defaultCalendar = $this->manager
                    ->getRepository('OroCalendarBundle:Calendar')
                    ->findDefaultCalendar(
                        $this->securityFacade->getLoggedUser()->getId(),
                        $this->securityFacade->getOrganization()->getId()
                    );
                $entity->setCalendar($defaultCalendar);
            } else {
                throw new \LogicException('Current user did not define');
            }
        }

        $this->form->setData($entity);

        if (in_array($this->request->getMethod(), array('POST', 'PUT'))) {
            $this->form->submit($this->request);

            if ($this->form->isValid()) {
                $targetEntityClass = $this->entityRoutingHelper->getEntityClassName($this->request);

                if ($targetEntityClass) {
                    $targetEntityId    = $this->entityRoutingHelper->getEntityId($this->request);
                    $targetEntity = $this->entityRoutingHelper->getEntityReference($targetEntityClass, $targetEntityId);
                    $action = $this->entityRoutingHelper->getAction($this->request);

                    if ($action === 'activity') {
                        $this->activityManager->addActivityTarget($entity, $targetEntity);
                    }

                    if ($action === 'assign'
                        && $targetEntity instanceof User
                        && $targetEntityId !== $this->securityFacade->getLoggedUserId()
                    ) {
                        /** @var Calendar $defaultCalendar */
                        $defaultCalendar = $this->manager
                            ->getRepository('OroCalendarBundle:Calendar')
                            ->findDefaultCalendar($targetEntity->getId(), $targetEntity->getOrganization()->getId());
                        $entity->setCalendar($defaultCalendar);
                    }
                }

                $this->onSuccess($entity);

                return true;
            }
        }

        return false;
    }

    /**
     * "Success" form handler
     *
     * @param CalendarEvent $entity
     */
    protected function onSuccess(CalendarEvent $entity)
    {
        $this->manager->persist($entity);
        $this->manager->flush();
    }
}
