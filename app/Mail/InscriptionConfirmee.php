<?php

namespace App\Mail;

use App\Models\Participant;
use App\Models\Reglage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Accuse de reception de l'inscription.
 *
 * Le sujet et le corps viennent des reglages : c'est un message qui part au nom
 * de l'organisation, ses mots doivent pouvoir changer sans redeploiement.
 */
class InscriptionConfirmee extends Mailable
{
    public function __construct(public Participant $participant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) Reglage::valeur(
                'email.inscription_sujet',
                'Votre inscription est enregistree',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inscription',
            with: [
                'nom'      => $this->participant->nom_affiche,
                'corps'    => (string) Reglage::valeur('email.inscription_corps', ''),
                'tournoi'  => (string) Reglage::valeur('tournoi.nom', 'HerboQuiz'),
                'debut'    => (string) Reglage::valeur('tournoi.debut', ''),
                'site'     => (string) Reglage::valeur('tournoi.url', config('app.url')),
                'signature' => (string) Reglage::valeur('tournoi.organisateur', ''),
            ],
        );
    }
}
