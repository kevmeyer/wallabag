<?php

namespace Wallabag\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Wallabag\CoreBundle\Entity\Entry;
use Wallabag\CoreBundle\Form\Type\NewEntryType;
use Wallabag\CoreBundle\Form\Type\EditEntryType;
use Wallabag\CoreBundle\Filter\EntryFilterType;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

class EntryController extends Controller
{
    /**
     * @param Request $request
     *
     * @Route("/new-entry", name="new_entry")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addEntryFormAction(Request $request)
    {
        $entry = new Entry($this->getUser());

        $form = $this->createForm(new NewEntryType(), $entry);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $entry = $this->get('wallabag_core.content_proxy')->updateEntry($entry, $entry->getUrl());

            $em = $this->getDoctrine()->getManager();
            $em->persist($entry);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'notice',
                'Entry saved'
            );

            return $this->redirect($this->generateUrl('homepage'));
        }

        return $this->render('WallabagCoreBundle:Entry:new_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @param Request $request
     *
     * @Route("/new", name="new")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addEntryAction(Request $request)
    {
        return $this->render('WallabagCoreBundle:Entry:new.html.twig');
    }

    /**
     * Edit an entry content.
     *
     * @param Request $request
     * @param Entry   $entry
     *
     * @Route("/edit/{id}", requirements={"id" = "\d+"}, name="edit")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editEntryAction(Request $request, Entry $entry)
    {
        $this->checkUserAction($entry);

        $form = $this->createForm(new EditEntryType(), $entry);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entry);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'notice',
                'Entry updated'
            );

            return $this->redirect($this->generateUrl('view', array('id' => $entry->getId())));
        }

        return $this->render('WallabagCoreBundle:Entry:edit.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Shows all entries for current user.
     *
     * @param Request $request
     * @param int     $page
     *
     * @Route("/all/list/{page}", name="all", defaults={"page" = "1"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAllAction(Request $request, $page)
    {
        return $this->showEntries('all', $request, $page);
    }

    /**
     * Shows unread entries for current user.
     *
     * @param Request $request
     * @param int     $page
     *
     * @Route("/unread/list/{page}", name="unread", defaults={"page" = "1"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showUnreadAction(Request $request, $page)
    {
        return $this->showEntries('unread', $request, $page);
    }

    /**
     * Shows read entries for current user.
     *
     * @param Request $request
     * @param int     $page
     *
     * @Route("/archive/list/{page}", name="archive", defaults={"page" = "1"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showArchiveAction(Request $request, $page)
    {
        return $this->showEntries('archive', $request, $page);
    }

    /**
     * Shows starred entries for current user.
     *
     * @param Request $request
     * @param int     $page
     *
     * @Route("/starred/list/{page}", name="starred", defaults={"page" = "1"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showStarredAction(Request $request, $page)
    {
        return $this->showEntries('starred', $request, $page);
    }

    /**
     * Global method to retrieve entries depending on the given type
     * It returns the response to be send.
     *
     * @param string  $type    Entries type: unread, starred or archive
     * @param Request $request
     * @param int     $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function showEntries($type, Request $request, $page)
    {
        $repository = $this->getDoctrine()->getRepository('WallabagCoreBundle:Entry');

        switch ($type) {
            case 'starred':
                $qb = $repository->getBuilderForStarredByUser($this->getUser()->getId());
                break;

            case 'archive':
                $qb = $repository->getBuilderForArchiveByUser($this->getUser()->getId());
                break;

            case 'unread':
                $qb = $repository->getBuilderForUnreadByUser($this->getUser()->getId());
                break;

            case 'all':
                $qb = $repository->getBuilderForAllByUser($this->getUser()->getId());
                break;

            default:
                throw new \InvalidArgumentException(sprintf('Type "%s" is not implemented.', $type));
        }

        $form = $this->get('form.factory')->create(new EntryFilterType($repository, $this->getUser()));

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagerAdapter = new DoctrineORMAdapter($qb->getQuery());
        $entries = new Pagerfanta($pagerAdapter);

        $entries->setMaxPerPage($this->getUser()->getConfig()->getItemsPerPage());
        $entries->setCurrentPage($page);

        return $this->render(
            'WallabagCoreBundle:Entry:entries.html.twig',
            array(
                'form' => $form->createView(),
                'entries' => $entries,
                'currentPage' => $page,
            )
        );
    }

    /**
     * Shows entry content.
     *
     * @param Entry $entry
     *
     * @Route("/view/{id}", requirements={"id" = "\d+"}, name="view")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(Entry $entry)
    {
        $this->checkUserAction($entry);

        return $this->render(
            'WallabagCoreBundle:Entry:entry.html.twig',
            array('entry' => $entry)
        );
    }

    /**
     * Changes read status for an entry.
     *
     * @param Request $request
     * @param Entry   $entry
     *
     * @Route("/archive/{id}", requirements={"id" = "\d+"}, name="archive_entry")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function toggleArchiveAction(Request $request, Entry $entry)
    {
        $this->checkUserAction($entry);

        $entry->toggleArchive();
        $this->getDoctrine()->getManager()->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            'Entry '.($entry->isArchived() ? 'archived' : 'unarchived')
        );

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * Changes favorite status for an entry.
     *
     * @param Request $request
     * @param Entry   $entry
     *
     * @Route("/star/{id}", requirements={"id" = "\d+"}, name="star_entry")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function toggleStarAction(Request $request, Entry $entry)
    {
        $this->checkUserAction($entry);

        $entry->toggleStar();
        $this->getDoctrine()->getManager()->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            'Entry '.($entry->isStarred() ? 'starred' : 'unstarred')
        );

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * Deletes entry and redirect to the homepage.
     *
     * @param Entry $entry
     *
     * @Route("/delete/{id}", requirements={"id" = "\d+"}, name="delete_entry")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteEntryAction(Entry $entry)
    {
        $this->checkUserAction($entry);

        $em = $this->getDoctrine()->getManager();
        $em->remove($entry);
        $em->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            'Entry deleted'
        );

        return $this->redirect($this->generateUrl('homepage'));
    }

    /**
     * Check if the logged user can manage the given entry.
     *
     * @param Entry $entry
     */
    private function checkUserAction(Entry $entry)
    {
        if ($this->getUser()->getId() != $entry->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can not access this entry.');
        }
    }
}
