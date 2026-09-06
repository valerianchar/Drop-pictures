/**
 * Table des URLs statiques. Les URLs de ressources (fichier, groupe, lien…)
 * sont fabriquées côté serveur et voyagent dans les payloads Inertia.
 */
export const routes = {
    dashboard: '/',
    login: '/connexion',
    register: '/inscription',
    logout: '/deconnexion',
    forgotPassword: '/mot-de-passe-oublie',
    resetPassword: '/nouveau-mot-de-passe',
    uploads: '/depots',
    groups: '/groupes',
    mediaTags: (mediaId) => `/fichiers/${mediaId}/tags`,
    mediaDelete: (mediaId) => `/fichiers/${mediaId}`,
    mediaShareLinks: (mediaId) => `/fichiers/${mediaId}/liens`,
};
