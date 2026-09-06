<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inscriptions ouvertes
    |--------------------------------------------------------------------------
    |
    | Une instance personnelle peut vouloir fermer la porte une fois ses proches
    | inscrits. Les invitations de groupe restent honorées : un invité crée son
    | compte par le lien reçu même quand l'inscription libre est fermée.
    |
    */

    'registration_open' => (bool) env('DROP_REGISTRATION_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Quota par utilisateur
    |--------------------------------------------------------------------------
    |
    | Espace offert à chaque compte, en octets. Les fichiers sont conservés tels
    | qu'ils ont été déposés — aucune compression ne vient jamais « gagner » de
    | la place —, le quota est donc la somme exacte des tailles d'origine.
    | 10 Go par défaut.
    |
    */

    'quota_bytes' => (int) env('DROP_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Taille maximale d'un fichier
    |--------------------------------------------------------------------------
    |
    | 5 Go par défaut : de quoi accueillir une vidéo 4K ProRes ou une série RAW.
    | Le fichier arrive par morceaux, jamais en une seule requête ; les limites
    | PHP (upload_max_filesize, post_max_size) ne portent que sur un morceau.
    |
    */

    'max_file_bytes' => (int) env('DROP_MAX_FILE_BYTES', 5 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Taille d'un morceau
    |--------------------------------------------------------------------------
    |
    | Chaque morceau part dans sa propre requête et s'ajoute au fichier en
    | cours. 8 Mo tient largement sous post_max_size (64 Mo dans l'image) tout
    | en limitant le nombre d'allers-retours pour un gros fichier.
    |
    */

    'chunk_bytes' => (int) env('DROP_CHUNK_BYTES', 8 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Dépôts abandonnés
    |--------------------------------------------------------------------------
    |
    | Un dépôt interrompu — onglet fermé, réseau coupé — laisse un fichier
    | partiel. Passé ce délai en heures, il est purgé par le planificateur.
    |
    */

    'stale_upload_hours' => (int) env('DROP_STALE_UPLOAD_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Durée d'un lien à durée limitée
    |--------------------------------------------------------------------------
    |
    | En jours. Un lien sans limite ne périme jamais.
    |
    */

    'share_link_days' => (int) env('DROP_SHARE_LINK_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Miniatures
    |--------------------------------------------------------------------------
    |
    | Largeur des aperçus dérivés. Les aperçus sont les SEULS dérivés produits :
    | le fichier d'origine n'est jamais touché ni réencodé.
    |
    */

    'thumbnail_width' => (int) env('DROP_THUMBNAIL_WIDTH', 640),

    /*
    |--------------------------------------------------------------------------
    | ffmpeg / ffprobe
    |--------------------------------------------------------------------------
    |
    | Chemins des binaires qui lisent les dimensions et la durée des vidéos et
    | en extraient une image d'aperçu. Laissés vides, les vidéos sont acceptées
    | sans aperçu ni métadonnées — jamais refusées.
    |
    */

    'ffprobe' => env('DROP_FFPROBE', 'ffprobe'),
    'ffmpeg' => env('DROP_FFMPEG', 'ffmpeg'),

];
