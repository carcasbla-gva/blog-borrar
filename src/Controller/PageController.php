<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;       // <--- AÑADIR ESTA LÍNEA
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Contact;
use App\Form\ContactFormType;                        // <--- AÑADIR ESTA LÍNEA
use App\Entity\Category;
use App\Entity\Post;
use App\Form\PostFormType;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class PageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Category::class);
        $categories = $repository->findAll();

        return $this->render('index.html.twig', [
            'categories' => $categories,
        ]);
    }


    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig', []);
    }

    // #[Route('/contact', name: 'contact')]
    // public function contact(): Response
    // {
    //     return $this->render('contact.html.twig', []);
    // }
    #[Route('/blog/{page}', name: 'blog', requirements: ['page' => '\d+'], defaults: ['page' => 1])]
    public function blog(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();
        $recents = $repository->findRecents();
        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
        ]);
    }

    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, string $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(['slug' => $slug]);
        $recents = $repository->findRecents();
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
        ]);
    }

    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contacto = $form->getData();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($contacto);
            $entityManager->flush();
            return $this->redirectToRoute('thankyou', []);
        }
        return $this->render('contact.html.twig', array(
            'form' => $form->createView()
        ));
    }
    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response
    {
        return $this->render('thankyou.html.twig', []);
    }
    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        // Punto 4 del reto: comprobar si inició sesión
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Punto 1 del reto: procesar y subir el archivo físico
            $file = $form->get('image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move($this->getParameter('posts_directory'), $newFilename);
                } catch (FileException $e) {
                    return new Response("Error al subir el archivo: " . $e->getMessage());
                }

                $post->setImage($newFilename);
            }

            $post = $form->getData();
            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setPostUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ['slug' => $post->getSlug()]);
        }

        return $this->render('blog/new_post.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
