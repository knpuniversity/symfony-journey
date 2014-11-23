<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Dinosaur;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class DinosaurController extends Controller
{
    /**
     * @Route("/", name="dinosaur_list")
     */
    public function indexAction()
    {
        $dinos = $this->getDoctrine()
            ->getRepository('AppBundle:Dinosaur')
            ->findAll();

        return $this->render('dinosaurs/index.html.twig', [
            'dinos' => $dinos,
        ]);
    }

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id)
    {
        $dino = $this->getDoctrine()
            ->getRepository('AppBundle:Dinosaur')
            ->find($id);

        if (!$dino) {
            throw $this->createNotFoundException('That dino is extinct!');
        }

        return $this->render('dinosaurs/show.html.twig', [
            'dino' => $dino,
        ]);
    }
} 