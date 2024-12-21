<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Article;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Form\LigneCommandeType;
use App\Repository\ClientRepository;
use App\Repository\ArticleRepository;
use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommandeController extends AbstractController
{
    #[Route('/commande/nouvelle', name: 'commande.create')]
    public function create(Request $request, ClientRepository $clientRepo, ArticleRepository $articleRepo, EntityManagerInterface $em): Response
    {
        $telephone = $request->query->get('telephone');
        $client = null;
        $articles = $articleRepo->findAll();
        $ligneCommandes = $request->getSession()->get('ligneCommandes', []);

        // Récup des prix et stocks
        $prixData = [];
        $stockData = [];
        foreach ($articles as $article) {
            $prixData[$article->getId()] = $article->getPrix();
            $stockData[$article->getId()] = $article->getQteStock();
        }

        // Form de ligne de commande
        $formLigneCommande = $this->createForm(LigneCommandeType::class, null, [
            'prix_data' => $prixData,
            'stock_data' => $stockData,
        ]);
        $formLigneCommande->handleRequest($request);

        if ($telephone) {
            $client = $clientRepo->findOneBy(['telephone' => $telephone]);
            if (!$client) {
                $this->addFlash('danger', "Client introuvable avec le téléphone : $telephone");
            }
        } else {
            $client = null; 
        }
        

        
        if ($formLigneCommande->isSubmitted() && $formLigneCommande->isValid()) {
            $data = $formLigneCommande->getData();
            $article = $data['article'];
            $quantite = $data['quantite'];

            if ($quantite > $article->getQteStock()) {
                $this->addFlash('danger', 'Quantité demandée dépasse le stock disponible.');
                return $this->redirectToRoute('commande.create', ['telephone' => $telephone]);
            }

            
            $article->setQteStock($article->getQteStock() - $quantite);
            $em->persist($article);

            $montant = $article->getPrix() * $quantite;
            $ligneCommandes[] = [
                'article' => $article,
                'quantite' => $quantite,
                'prixUnitaire' => $article->getPrix(),
                'montant' => $montant,
            ];

            $request->getSession()->set('ligneCommandes', $ligneCommandes);
            $em->flush();
        }

        return $this->render('commande/create.html.twig', [
            'client' => $client,
            'articles' => $articles,
            'formLigneCommande' => $formLigneCommande->createView(),
            'ligneCommandes' => $ligneCommandes,
            'total' => array_sum(array_column($ligneCommandes, 'montant')),
        ]);
    }

    #[Route('/commande/valider', name: 'commande.validate')]
    public function validate(Request $request, EntityManagerInterface $em): Response
    {
        $ligneCommandes = $request->getSession()->get('ligneCommandes', []);
        if (!$ligneCommandes) {
            $this->addFlash('danger', 'Aucune ligne de commande trouvée.');
            return $this->redirectToRoute('commande.create');
        }
    
        $clientId = $request->query->get('clientId');
        $client = $em->getRepository(Client::class)->find($clientId);
        if (!$client) {
            $this->addFlash('danger', 'Client introuvable.');
            return $this->redirectToRoute('commande.create');
        }
    
        $commande = new Commande();
        $commande->setClient($client);
        $commande->setDateAt(new \DateTimeImmutable());
    
        $total = 0;
        foreach ($ligneCommandes as $ligneData) {
            $article = $em->getRepository(Article::class)->find($ligneData['article']->getId());
            if (!$article) {
                throw new \Exception("Article non trouvé pour l'ID " . $ligneData['article']->getId());
            }
    
            $ligne = new LigneCommande();
            $ligne->setCommande($commande);
            $ligne->setArticle($article);
            $ligne->setQuantite($ligneData['quantite']);
            $ligne->setPrixUnitaire($ligneData['prixUnitaire']);
            $ligne->setMontant($ligneData['montant']);
    
            $total += $ligneData['montant'];
    
            $em->persist($ligne);
        }
    
        $commande->setTotal($total);
    
        $em->persist($commande);
        $em->flush();
    
        $request->getSession()->remove('ligneCommandes');
        $this->addFlash('success', 'Commande validée avec succès !');
    
        return $this->redirectToRoute('commande.list');
    }
    

    #[Route('/commande/liste', name: 'commande.list')]
    public function list(EntityManagerInterface $em): Response
    {
        $commandes = $em->getRepository(Commande::class)->findAll();
    
        return $this->render('commande/list.html.twig', [
            'commandes' => $commandes,
        ]);
    }


    #[Route('/commande/{id}/edit', name: 'commande.edit')]
    public function edit(int $id, EntityManagerInterface $em, Request $request): Response 
    {
        $commande = $em->getRepository(Commande::class)->find($id);

        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirectToRoute('commande.list');
        }

        // je recup
        $ligneCommandes = $commande->getLignes();

        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            foreach ($ligneCommandes as $ligne) {
                $quantite = $data['quantite_' . $ligne->getId()] ?? $ligne->getQuantite();
                $prixUnitaire = $data['prixUnitaire_' . $ligne->getId()] ?? $ligne->getPrixUnitaire();

                // Je fais validation et update
                if ($quantite > 0 && $prixUnitaire > 0) {
                    $ligne->setQuantite((int) $quantite);
                    $ligne->setPrixUnitaire((float) $prixUnitaire);
                    $ligne->setMontant($quantite * $prixUnitaire);
                } else {
                    $this->addFlash('warning', 'Quantité ou prix invalide pour l\'article ' . $ligne->getArticle()->getLibelle());
                }
            }

            // Je mets a jour 
            $total = array_reduce($ligneCommandes->toArray(), function ($carry, LigneCommande $ligne) {
                return $carry + $ligne->getMontant();
            }, 0);

            $commande->setTotal($total);

            $em->flush();

            $this->addFlash('success', 'Commande mise à jour avec succès.');
            return $this->redirectToRoute('commande.list');
        }

        return $this->render('commande/edit.html.twig', [
            'commande' => $commande,
            'ligneCommandes' => $ligneCommandes,
        ]);
}

    
}
