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
     * Btw, this uses the ParamConvert to use the {id} to query for the Dinosaur entity
     *
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction(Dinosaur $dino)
    {
        return $this->render('dinosaurs/show.html.twig', [
            'dino' => $dino,
        ]);
    }
} 