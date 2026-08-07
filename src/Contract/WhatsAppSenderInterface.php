<?php

namespace App\Contract;

/**
 * Transport bas niveau vers WhatsApp.
 *
 * Volontairement limité aux templates : un message initié par l'entreprise
 * (hors fenêtre de 24h ouverte par le client) doit obligatoirement s'appuyer
 * sur un template pré-approuvé, il n'existe donc pas d'envoi de texte libre.
 */
interface WhatsAppSenderInterface
{
    /**
     * @param string        $to              Numéro du destinataire, normalisé par l'implémentation
     * @param string        $templateName    Nom du template approuvé côté fournisseur
     * @param list<string>  $bodyParameters  Variables {{1}}, {{2}}… du corps, dans l'ordre
     * @param list<array<string, mixed>> $extraComponents Composants supplémentaires (boutons, en-tête)
     *
     * @return bool true si le fournisseur a accepté le message
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        array $extraComponents = [],
    ): bool;

    /**
     * Indique si les identifiants nécessaires à l'envoi sont présents.
     */
    public function isConfigured(): bool;
}
