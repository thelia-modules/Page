<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Page\Controller\Admin;

use Page\Form\PageTypeForm;
use Page\Service\PageTypeProvider;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\TokenProvider;

/**
 * Class PageController.
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

#[Route('/admin/page-type', name: 'page_type')]
class PageTypeController extends BaseAdminController
{
    use ChecksAdminWriteAccess;

    #[Route('', name: '_list', methods: ['GET'])]
    public function listPageTypeAction()
    {
        $types = [];
        foreach (\Page\Model\PageTypeQuery::create()->orderById()->find() as $type) {
            $types[] = ['id' => $type->getId(), 'type' => $type->getType()];
        }

        return $this->render('page-type', [
            'page_types' => $types,
            'type_form' => $this->createForm(PageTypeForm::class)->getForm()->createView(),
        ]);
    }

    /**
     */
    #[Route('/create', name: '_create', methods: ['POST'])]
    public function createPageTypeAction(ParserContext $parserContext, PageTypeProvider $pageTypeProvider)
    {
        if (null !== $refusal = $this->refuseUnlessGranted(AccessManager::CREATE)) {
            return $refusal;
        }

        $form = $this->createForm(PageTypeForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $pageTypeProvider->createPageType(
                $formData['type']
            );

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    /**
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response|null
     */
    #[Route('/update/{pagesTypeId}', name: '_update', methods: ['POST'])]
    public function updatePagesTypeAction(ParserContext $parserContext, PageTypeProvider $pageTypeProvider, int $pagesTypeId)
    {
        if (null !== $refusal = $this->refuseUnlessGranted(AccessManager::UPDATE)) {
            return $refusal;
        }

        $form = $this->createForm(PageTypeForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $pageTypeProvider->updatePageType(
                $pagesTypeId,
                $formData['type']
            );

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    /**
     *
     * @return string|null
     */
    #[Route('/delete/{pagesTypeId}', name: '_delete', methods: ['POST'])]
    public function deletePagesTypeAction(Request $request, TokenProvider $tokenProvider, PageTypeProvider $pageTypeProvider, $pagesTypeId)
    {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::DELETE)) {
            return $refusal;
        }

        try {
            $pageTypeProvider->deletePageType($pagesTypeId);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();

            return $this->generateRedirect('/admin/page-type?error='.$error_message);
        }

        return $this->generateRedirect('/admin/page-type');
    }
}
