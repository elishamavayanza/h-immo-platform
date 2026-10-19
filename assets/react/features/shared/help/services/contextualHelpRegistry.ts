import { buildAppRoutes } from '../../../../app/routes/appRoutes.config.ts';
import { resolveSidebar } from '../../../../app/layout/MainLayout/sidebar/sidebar.config.ts';
import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types.ts';
import type { HelpQuestion, HelpRole, PageHelp } from '../types/help.types';

const ORG_ROLES: readonly OrganizationRole[] = ['patron', 'admin_immobilier', 'admin_ville'];
const PLATFORM_ROLE: PlatformRole = 'super_admin';

const content: Record<string, Omit<PageHelp, 'path' | 'roles'>> = {
    '/login': {
        title: 'Connexion',
        description: 'Connectez-vous à votre compte Soft-IMMO pour ouvrir votre espace autorisé.',
        questions: [
            { id: 'login-access', question: 'Comment me connecter ?', answer: 'Saisissez l’adresse e-mail et le mot de passe associés à votre compte, puis sélectionnez « Se connecter ». Après validation, l’application ouvre l’espace auquel votre compte a accès.' },
            { id: 'login-error', question: 'Que faire si la connexion échoue ?', answer: 'Vérifiez l’adresse et le mot de passe, puis réessayez. Après plusieurs tentatives, une limitation temporaire peut s’appliquer. Si un message indique que le compte ou l’organisation est suspendu, suivez les informations de durée affichées ou contactez votre administrateur.' },
            { id: 'login-theme', question: 'Comment changer le thème de connexion ?', answer: 'Utilisez le sélecteur clair/sombre situé sur la carte de connexion. Le thème sélectionné est conservé dans les préférences de l’appareil.' },
            { id: 'login-password', question: 'Comment récupérer mon mot de passe ?', answer: 'Sélectionnez « Mot de passe oublié ? », indiquez votre adresse e-mail et suivez le lien reçu. Pour protéger les comptes, la confirmation affichée ne révèle pas si une adresse est enregistrée.' },
        ],
    },
    '/forgot-password': {
        title: 'Mot de passe oublié', description: 'Demandez un lien sécurisé pour réinitialiser votre mot de passe.',
        questions: [
            { id: 'forgot-request', question: 'Comment demander un lien de réinitialisation ?', answer: 'Saisissez votre adresse e-mail puis validez le formulaire. Si un compte correspondant existe, un lien de réinitialisation sera envoyé.' },
            { id: 'forgot-no-email', question: 'Que faire si je ne reçois pas le message ?', answer: 'Vérifiez les courriers indésirables et l’orthographe de l’adresse, puis réessayez après le délai indiqué. Le message de confirmation reste volontairement identique pour toutes les adresses.' },
        ],
    },
    '/reset-password': {
        title: 'Nouveau mot de passe', description: 'Définissez un nouveau mot de passe avec le lien reçu par e-mail.',
        questions: [
            { id: 'reset-token', question: 'Pourquoi le lien ne fonctionne-t-il plus ?', answer: 'Le lien contient un jeton temporaire et à usage limité. S’il a expiré ou a déjà été utilisé, revenez à « Mot de passe oublié » pour demander un nouveau lien.' },
            { id: 'reset-form', question: 'Comment valider le nouveau mot de passe ?', answer: 'Saisissez le nouveau mot de passe et sa confirmation en respectant les règles affichées, puis validez. Connectez-vous ensuite avec ce nouveau mot de passe.' },
        ],
    },
    '/app/access-denied': {
        title: 'Accès refusé', description: 'Comprenez pourquoi une page n’est pas accessible avec l’espace ou le rôle actif.',
        questions: [
            { id: 'denied-why', question: 'Pourquoi l’accès à cette page est-il refusé ?', answer: 'La page ne fait pas partie des accès de votre rôle dans l’espace actif, ou votre compte ne dispose pas d’une organisation attribuée. Les permissions sont vérifiées par l’application et le serveur.' },
            { id: 'denied-next', question: 'Comment retrouver mon espace ?', answer: 'Utilisez le bouton « Accueil » pour revenir à la destination de votre rôle. Si vous pensez qu’un accès manque, demandez à l’administrateur de vérifier votre rôle et les villes attribuées.' },
        ],
    },
    '/app/admin/audit': {
        title: "Journal d'audit",
        description: 'Consultez les opérations sensibles enregistrées sur la plateforme.',
        questions: [
            { id: 'audit-purpose', question: 'Que montre le journal d’audit ?', answer: 'Il présente les événements administratifs enregistrés, avec leur date, leur type et les éléments de contexte disponibles. Il sert à retracer les changements et à faciliter les vérifications.' },
            { id: 'audit-details', question: 'Comment consulter le détail d’un événement ?', answer: 'Sélectionnez une ligne du journal pour ouvrir son panneau de détail. Les valeurs sensibles, comme les mots de passe et les jetons, ne sont pas affichées.' },
            { id: 'audit-scope', question: 'Pourquoi certaines actions ne figurent-elles pas ici ?', answer: 'Le journal ne contient que les événements effectivement audités et visibles dans le périmètre plateforme. Il ne remplace pas les journaux techniques du serveur.' },
        ],
    },
    '/app/admin/dashboard': {
        title: 'Tableau de bord plateforme',
        description: 'Vue de synthèse de l’activité et de l’état de la plateforme.',
        questions: [
            { id: 'platform-kpis', question: 'Comment lire les indicateurs ?', answer: 'Les cartes et graphiques résument les données plateforme chargées par les services de tableau de bord. Une variation ou un total dépend des données présentes et de la période affichée.' },
            { id: 'platform-data', question: 'Pourquoi un graphique est-il vide ?', answer: 'Vérifiez que des données existent sur la période. Utilisez l’action de réessai si la page signale une erreur de chargement ; si le problème persiste, transmettez l’identifiant de l’erreur au support.' },
        ],
    },
    '/app/admin/organisations': {
        title: 'Organisations',
        description: 'Gérez les organisations et leur état d’accès à la plateforme.',
        questions: [
            { id: 'org-find', question: 'Comment retrouver une organisation ?', answer: 'Utilisez la recherche et les filtres visibles dans la liste. Ouvrez ensuite la ligne correspondante pour consulter ou modifier sa fiche.' },
            { id: 'org-create', question: 'Comment créer ou modifier une organisation ?', answer: 'Utilisez l’action de création ou d’édition affichée sur la page, renseignez les champs obligatoires de chaque étape puis validez. Vérifiez ensuite que l’organisation apparaît avec les informations attendues.' },
            { id: 'org-capacity-configure', question: 'Comment attribuer des capacités à une organisation ?', answer: 'À la création ou dans l’édition d’une organisation, définissez un plafond positif pour les villes, parcelles, bâtiments, unités et membres actifs, ou cochez « Sans limite ». Ces valeurs sont configurées manuellement par le SUPER_ADMIN et auditées. La réduction d’un plafond ne supprime rien : elle bloque seulement les nouvelles créations lorsque l’usage atteint le plafond.', keywords: ['offre', 'capacité', 'limite', 'plafond', 'ville', 'parcelle', 'bâtiment', 'unité', 'membre', 'sans limite'] },
            { id: 'org-capacity-existing-data', question: 'Que se passe-t-il si un plafond est inférieur à l’usage actuel ?', answer: 'Les éléments déjà enregistrés restent visibles et modifiables. Les créations supplémentaires de la ressource concernée sont refusées avec un message indiquant la limite et invitant à contacter le SUPER_ADMIN. Aucune donnée n’est supprimée.', keywords: ['dépassement', 'réduction', 'création', 'données', 'usage'] },
            { id: 'org-suspend', question: 'Comment suspendre ou réactiver une organisation ?', answer: 'Ouvrez l’action de suspension de l’organisation, choisissez la durée proposée et indiquez le motif demandé. Pour réactiver avant la fin, utilisez l’action de réactivation de sa fiche. La suspension bloque l’accès selon les règles du compte.' },
            { id: 'org-errors', question: 'Pourquoi une organisation est-elle absente ou inaccessible ?', answer: 'Vérifiez les filtres et l’état du compte. Une organisation n’est visible que dans le périmètre d’administration autorisé ; les restrictions d’accès doivent être traitées par l’administration plateforme.' },
        ],
    },
    '/app/admin/organisations/nouvelle': {
        title: 'Créer une organisation',
        description: 'Configurez une nouvelle organisation en suivant les étapes de la page.',
        questions: [
            { id: 'org-create-steps', question: 'Que contient chaque étape de création ?', answer: 'La page répartit la saisie en quatre étapes : identité et coordonnées, capacités initiales, compte PATRON, puis logo. Utilisez « Continuer » pour avancer et « Étape précédente » pour revenir sans perdre les champs déjà saisis.', keywords: ['étapes', 'stepper', 'continuer', 'retour'] },
            { id: 'org-create-examples', question: 'À quoi servent les exemples dans les champs ?', answer: 'Les exemples gris sont des indications de format, par exemple pour le code, l’e-mail ou le téléphone. Ils disparaissent dès que vous commencez à saisir. Remplacez-les par les vraies informations de l’organisation.', keywords: ['exemple', 'placeholder', 'champ', 'saisie'] },
            { id: 'org-create-limits', question: 'Comment choisir les capacités initiales ?', answer: 'Pour chaque ressource, laissez « Sans limite » ou décochez cette option et saisissez un plafond positif. Ces capacités peuvent être ajustées plus tard par le SUPER_ADMIN ; elles ne suppriment jamais les données existantes.', keywords: ['capacité', 'limite', 'plafond', 'ville', 'parcelle', 'bâtiment', 'unité', 'membre'] },
            { id: 'org-create-patron', question: 'Comment le PATRON reçoit-il l’accès ?', answer: 'Indiquez le nom, l’adresse e-mail et le téléphone du PATRON. L’organisation et ce compte sont créés ensemble ; un lien de configuration du mot de passe est envoyé à son adresse.', keywords: ['compte', 'patron', 'identifiant', 'mot de passe', 'e-mail'] },
            { id: 'org-create-logo', question: 'Le logo est-il obligatoire ?', answer: 'Non. Vous pouvez ajouter un logo à la dernière étape, ou créer l’organisation sans logo et le définir plus tard. Le recadrage proposé prépare l’image avant son envoi.', keywords: ['logo', 'image', 'facultatif', 'recadrer'] },
            { id: 'org-create-save', question: 'Quand l’organisation est-elle créée ?', answer: 'La création est confirmée à la dernière étape. Le backend enregistre l’organisation et son PATRON dans une même transaction. En cas de problème, la page reste ouverte et affiche l’erreur pour vous permettre de corriger les champs concernés.', keywords: ['enregistrer', 'valider', 'erreur', 'transaction'] },
        ],
    },
    '/app/admin/organisations/:uuid/modifier': {
        title: 'Modifier une organisation',
        description: 'Mettez à jour l’identité, les capacités, les options et le logo de l’organisation.',
        questions: [
            { id: 'org-edit-steps', question: 'Comment se déroule la modification ?', answer: 'La page présente trois étapes : informations générales, capacités et options, puis logo. Les valeurs actuelles sont préremplies ; avancez avec « Continuer » et revenez en arrière sans perdre vos changements.', keywords: ['étapes', 'stepper', 'modifier', 'continuer'] },
            { id: 'org-edit-limits', question: 'Que signifie l’utilisation affichée à côté des plafonds ?', answer: 'Elle indique le nombre de ressources déjà enregistrées. Si un nouveau plafond est inférieur à cette utilisation, les éléments existants restent intacts, mais de nouvelles créations de cette ressource seront bloquées jusqu’à l’augmentation de la limite.', keywords: ['usage', 'utilisation', 'capacité', 'plafond', 'dépassement'] },
            { id: 'org-edit-showcase', question: 'Que fait l’option de vitrine immobilière ?', answer: 'Lorsqu’elle est activée, le menu Vitrine devient disponible aux rôles PATRON et ADMIN_IMMOBILIER de cette organisation. La désactivation retire cet accès organisationnel.', keywords: ['vitrine', 'option', 'menu', 'patron', 'admin immobilier'] },
            { id: 'org-edit-logo', question: 'Comment remplacer ou retirer le logo ?', answer: 'À l’étape Logo, choisissez une nouvelle image pour remplacer le logo ou utilisez l’action de suppression proposée par le sélecteur. Les autres informations sont enregistrées avec la validation finale.', keywords: ['logo', 'remplacer', 'retirer', 'supprimer'] },
            { id: 'org-edit-errors', question: 'Pourquoi ne puis-je pas modifier les plafonds ?', answer: 'Les contrôles de capacité restent désactivés si l’application ne peut pas charger l’utilisation actuelle. Revenez à la page ou réessayez quand la connexion est rétablie afin de prévenir une réduction sans aperçu.', keywords: ['plafond', 'verrouillé', 'chargement', 'erreur'] },
        ],
    },
    '/app/admin/rapports': {
        title: 'Rapports plateforme',
        description: 'Consultez les synthèses globales disponibles pour le Super Admin.',
        questions: [
            { id: 'platform-reports', question: 'D’où viennent les chiffres ?', answer: 'Les chiffres sont calculés par les services de rapport à partir des données enregistrées. Ils reflètent le périmètre et les filtres actuellement sélectionnés.' },
            { id: 'platform-report-export', question: 'Comment exporter un rapport ?', answer: 'Si un bouton d’export est proposé sur la page, choisissez d’abord les filtres souhaités puis lancez l’export. Si l’action n’est pas affichée, aucun export n’est disponible dans cette vue.' },
            { id: 'platform-report-empty', question: 'Pourquoi le rapport ne contient-il pas de données ?', answer: 'La période sélectionnée peut ne comporter aucune activité. Vérifiez les filtres et réessayez ; les données apparaissent après leur enregistrement par les espaces concernés.' },
        ],
    },
    '/app/admin/publications': {
        title: 'Publications',
        description: 'Rédigez, ciblez et diffusez les annonces de la plateforme.',
        questions: [
            { id: 'publications-draft', question: 'Comment préparer une publication ?', answer: 'Renseignez le titre et le contenu, choisissez une catégorie et enregistrez le brouillon. Vous pouvez ajouter des images et consulter leur aperçu après l’enregistrement.' },
            { id: 'publications-audience', question: 'Comment choisir les destinataires ?', answer: 'Choisissez tous les utilisateurs métier actifs, certains rôles métier ou une organisation. Les comptes SUPER_ADMIN gèrent les annonces et ne font pas partie des destinataires.' },
            { id: 'publications-schedule', question: 'Comment programmer ou limiter la durée d’une annonce ?', answer: 'Indiquez une date de début pour programmer la diffusion et, facultativement, une date de fin. Sans date de début future, la publication peut être diffusée immédiatement.' },
            { id: 'publications-publish', question: 'Comment diffuser, retirer ou archiver une publication ?', answer: 'Enregistrez d’abord le brouillon, puis sélectionnez « Publier maintenant » lorsque son contenu et ses destinataires sont corrects. Une publication diffusée peut ensuite être retirée ou archivée depuis son historique.' },
            { id: 'publications-images', question: 'Quelles images puis-je joindre ?', answer: 'Vous pouvez joindre jusqu’à huit images dans les formats acceptés par le formulaire. Une légende peut être saisie pour chaque image sélectionnée.' },
        ],
    },
    '/app/publications': {
        title: 'Détail d’une publication',
        description: 'Lisez une annonce diffusée à votre organisation et revenez à votre espace de travail.',
        questions: [
            { id: 'publication-read', question: 'Comment lire une publication et revenir à mon espace ?', answer: 'Parcourez le titre, la date, le contenu et les images, puis utilisez « Retour à l’application ». L’ouverture marque automatiquement la publication comme lue et met à jour le compteur de notifications.', keywords: ['annonce', 'notification', 'lue', 'compteur', 'retour'] },
            { id: 'publication-unavailable', question: 'Pourquoi la publication est-elle indisponible ?', answer: 'Elle a pu expirer, être retirée ou ne pas vous être destinée. Revenez à votre espace ; si elle reste dans vos notifications, actualisez celles-ci ou contactez l’administration de la plateforme.', keywords: ['expirée', 'retirée', 'erreur', 'accès'] },
            { id: 'publication-images', question: 'Comment consulter les images jointes ?', answer: 'Les images s’affichent dans le contenu de la publication et s’adaptent à la largeur de l’écran. Faites défiler la page pour consulter l’ensemble de l’annonce.', keywords: ['photo', 'image', 'mobile', 'défilement'] },
        ],
    },
    '/app/admin/utilisateurs': {
        title: 'Utilisateurs plateforme',
        description: 'Consultez les comptes et gérez leur accès à la plateforme.',
        questions: [
            { id: 'users-search', question: 'Comment retrouver un compte ?', answer: 'Recherchez l’utilisateur dans la liste et utilisez les filtres disponibles. Ouvrez sa ligne ou son menu d’actions pour voir les options permises.' },
            { id: 'users-suspend', question: 'Comment suspendre puis réactiver un compte ?', answer: 'Depuis les actions du compte, choisissez la suspension, indiquez la durée et le motif si le formulaire le demande, puis confirmez. La réactivation est possible depuis le même compte avant ou après l’échéance.' },
            { id: 'users-access', question: 'Pourquoi un utilisateur ne voit-il pas son espace ?', answer: 'Son accès dépend de l’état du compte et de ses appartenances à une organisation ou à une ville. Vérifiez ces rattachements et contactez le responsable de l’organisation si un accès métier manque.' },
        ],
    },
    '/app/dashboard': {
        title: 'Tableau de bord',
        description: 'Retrouvez les indicateurs utiles pour suivre votre périmètre de gestion.',
        questions: [
            { id: 'dashboard-kpis', question: 'Comment interpréter les indicateurs ?', answer: 'Chaque indicateur résume les données chargées pour votre périmètre : organisation pour le Patron et l’Administrateur immobilier, villes attribuées pour l’Administrateur de ville. Les montants conservent leur devise.' },
            { id: 'dashboard-charts', question: 'Comment lire les graphiques ?', answer: 'Les graphiques représentent l’évolution ou la répartition des données disponibles. Consultez leur titre et leur légende ; une valeur absente signifie généralement qu’aucune donnée correspondante n’est enregistrée.' },
            { id: 'dashboard-refresh', question: 'Que faire si les chiffres semblent incomplets ?', answer: 'Vérifiez l’organisation ou la ville active, puis rechargez la page. Les indicateurs ne peuvent présenter que les données auxquelles votre rôle a accès.' },
        ],
    },
    '/app/depenses': {
        title: 'Trésorerie',
        description: 'Suivez les entrées, les sorties et les soldes visibles dans votre périmètre.',
        questions: [
            { id: 'treasury-summary', question: 'Que représente le solde de trésorerie ?', answer: 'La synthèse regroupe les mouvements disponibles dans le périmètre et la période indiqués. Consultez les devises séparément : des montants USD et CDF ne doivent pas être additionnés sans conversion affichée.' },
            { id: 'treasury-movements', question: 'Comment retrouver une entrée ou une sortie ?', answer: 'Parcourez les mouvements et utilisez les filtres de période, de type ou de recherche qui apparaissent dans cette page. Ouvrez un mouvement pour lire sa provenance.' },
            { id: 'treasury-create', question: 'Où enregistrer une dépense ?', answer: 'Depuis la trésorerie, ouvrez la gestion des dépenses à l’aide du lien ou de l’action affichée. Le formulaire d’enregistrement demande les informations du mouvement et valide les champs obligatoires avant envoi.' },
            { id: 'treasury-scope', question: 'Pourquoi les montants diffèrent-ils selon la ville ?', answer: 'Le rôle et la ville active bornent les données consultées. Choisissez une autre ville attribuée dans le sélecteur d’espace lorsque cette possibilité est disponible.' },
        ],
    },
    '/app/depenses/gestion': {
        title: 'Gestion des dépenses',
        description: 'Consultez et enregistrez les dépenses de votre périmètre autorisé.',
        questions: [
            { id: 'expenses-create', question: 'Comment enregistrer une dépense ?', answer: 'Cliquez sur « Enregistrer une dépense » si l’action est disponible, choisissez les références proposées, saisissez la description, la catégorie, le montant et la devise, puis validez. La nouvelle dépense doit apparaître dans l’historique.' },
            { id: 'expenses-date', question: 'Quelle date indiquer pour une dépense saisie plus tard ?', answer: 'Indiquez la date réelle à laquelle la dépense a été engagée ou payée, même si elle est antérieure au jour de saisie. Le système conserve séparément la date d’enregistrement et l’utilisateur connecté.' },
            { id: 'expenses-search', question: 'Comment filtrer l’historique ?', answer: 'Saisissez un terme dans la recherche et choisissez une catégorie ou une date si ces filtres sont affichés. Effacez les filtres pour revenir à toute la liste autorisée.' },
            { id: 'expenses-cancel', question: 'Peut-on annuler une dépense ?', answer: 'Si l’action d’annulation est proposée pour la ligne, utilisez-la puis confirmez. Une dépense annulée reste identifiable dans l’historique conformément à la traçabilité comptable.' },
            { id: 'expenses-access', question: 'Pourquoi le bouton d’enregistrement est-il absent ?', answer: 'Les actions affichées dépendent de votre rôle et des autorisations accordées par l’organisation. L’aide ne peut pas accorder une permission ; demandez à votre responsable de vérifier votre accès.' },
        ],
    },
    '/app/location/locataires': {
        title: 'Locataires',
        description: 'Consultez les locataires et les indicateurs locatifs de votre périmètre. Les opérations locales sont réalisées par l’ADMIN_VILLE affecté.',
        questions: [
            { id: 'tenants-find', question: 'Comment rechercher et filtrer les locataires ?', answer: 'Recherchez par nom ou information affichée, puis filtrez par type, occupation actuelle, plusieurs logements, bail finissant, départ annoncé, mouvement des 30 derniers jours ou mouvement saisi après sa date effective. Les filtres s’appuient sur les baux et le dernier mouvement fournis par l’API ; si la liste est tronquée, le message sous le titre le signale. Les résultats restent limités à votre organisation ou aux villes autorisées.' },
            { id: 'tenants-create', question: 'Qui peut ajouter un locataire ?', answer: 'L’ADMIN_VILLE affecté à la ville enregistre les nouvelles fiches et les opérations locales. Le PATRON et l’ADMIN_IMMOBILIER consultent la vue consolidée ; ils ne créent pas les fiches au quotidien.' },
            { id: 'tenants-lease', question: 'Qui crée ou modifie un bail ?', answer: 'L’ADMIN_VILLE de la ville concernée prépare et gère le bail. Le PATRON et l’ADMIN_IMMOBILIER peuvent consulter les informations autorisées, sans déclencher l’opération.' },
            { id: 'tenants-multiple-homes', question: 'Une même personne peut-elle avoir plusieurs logements ?', answer: 'Oui. Une personne conserve une seule fiche et peut avoir plusieurs contrats ou périodes d’occupation. L’ADMIN_VILLE enregistre les liens et mouvements locaux ; les loyers et paiements restent rattachés à leur bail d’origine.' },
            { id: 'tenants-movements', question: 'À quoi sert l’historique résidentiel ?', answer: 'Il conserve les mouvements, leurs dates prévues ou réelles, leur motif et leur auteur. L’ADMIN_VILLE responsable de la ville effectue les entrées, départs, transferts, retours et corrections autorisées. Les rôles de supervision consultent les résultats et les traces.' },
            { id: 'tenants-transfer', question: 'Qui enregistre un transfert ?', answer: 'L’ADMIN_VILLE compétent prépare le bail de destination puis consigne le transfert dans l’historique. Le PATRON et l’ADMIN_IMMOBILIER suivent le changement ; ils ne réalisent pas eux-mêmes l’opération locale.' },
            { id: 'tenants-roommate-departure', question: 'Qui enregistre le départ d’un colocataire ?', answer: 'L’ADMIN_VILLE choisit la présence concernée, la date réelle et le motif, puis confirme. Seule cette période d’occupation est clôturée ; le bail et les autres personnes restent actifs.' },
            { id: 'tenants-roommate-return', question: 'Qui réintègre un occupant qui revient ?', answer: 'L’ADMIN_VILLE sélectionne l’ancienne présence dans un bail actif et indique la nouvelle date d’entrée. Une nouvelle période est créée après vérification des chevauchements ; l’ancienne reste dans l’historique.' },
            { id: 'tenants-transfer-finances', question: 'Les paiements sont-ils déplacés lors d’un transfert ?', answer: 'Non. Les loyers et paiements restent rattachés à leur bail d’origine. Le nouveau bail conserve les nouvelles conditions et aucun dépôt ni paiement n’est transféré automatiquement.' },
            { id: 'tenants-sharing', question: 'Puis-je enregistrer plusieurs colocataires sur un même bail ?', answer: 'Oui. Lors de la création du bail, ajoutez les personnes déjà enregistrées comme cotitulaires ou occupants, puis indiquez leurs dates propres. Le titulaire principal reste responsable des conditions financières ; une personne supplémentaire ne reçoit pas automatiquement une obligation de paiement.' },
            { id: 'tenants-correction', question: 'Qui peut corriger un mouvement ?', answer: 'L’ADMIN_VILLE peut corriger ou annuler un mouvement non réalisé avec un motif obligatoire. Les changements sont audités. Un mouvement réalisé est verrouillé pour protéger les contrats et les paiements.' },
            { id: 'tenants-archive', question: 'Qui peut archiver un locataire ?', answer: 'L’ADMIN_VILLE traite l’archivage dans sa ville lorsque les règles métier le permettent. Le PATRON et l’ADMIN_IMMOBILIER peuvent consulter les données mais ne réalisent pas cette opération locale.' },
        ],
    },
    '/app/location/loyers': {
        title: 'Loyers',
        description: 'Suivez les échéances, les retards et les paiements enregistrés.',
        questions: [
            { id: 'rents-status', question: 'Comment comprendre les états de loyer ?', answer: 'Un loyer peut être en attente, partiellement payé ou payé. Le retard est calculé à partir de l’échéance et du montant reçu ; il ne se saisit pas manuellement.' },
            { id: 'rents-payment', question: 'Comment enregistrer un paiement ?', answer: 'Ouvrez l’échéance concernée et utilisez l’action de paiement disponible. Vérifiez le montant, la devise, la date et la méthode avant validation. Un paiement validé constitue une trace comptable.' },
            { id: 'rents-payment-date', question: 'Puis-je enregistrer aujourd’hui un paiement effectué hier ?', answer: 'Oui. Dans « Date du paiement », choisissez le jour où le locataire a réellement payé, même si la saisie est faite plus tard. La période et l’échéance du loyer ne changent pas. L’historique conserve aussi automatiquement la date et l’auteur de l’enregistrement.' },
            { id: 'rents-payment-correction', question: 'Que faire si la date d’un paiement validé est incorrecte ?', answer: 'Un paiement validé est une trace comptable immuable. Utilisez l’action d’annulation disponible si vos droits le permettent, indiquez le motif, puis enregistrez un nouveau paiement avec la date réelle correcte. L’écriture initiale et sa contre-écriture restent dans l’historique.' },
            { id: 'rents-calendar', question: 'À quoi sert le calendrier ?', answer: 'Le calendrier permet de repérer les échéances et paiements par date. Sélectionnez une journée marquée pour filtrer les opérations correspondantes, puis effacez la date pour retrouver la liste complète.' },
            { id: 'rents-filter', question: 'Comment retrouver les impayés ?', answer: 'Utilisez le filtre de statut et la recherche disponibles dans la liste. La visibilité reste bornée à votre rôle et aux villes ou à l’organisation autorisées.' },
        ],
    },
    '/app/maintenance': {
        title: 'Maintenance',
        description: 'Planifiez les interventions et suivez leur avancement par ville et par bien.',
        questions: [
            { id: 'maintenance-create', question: 'Comment planifier une intervention ?', answer: 'Choisissez le type de bien puis le bien concerné, indiquez un titre, une description, une priorité et une date prévue si nécessaire. Sélectionnez un responsable lorsqu’il est proposé et créez l’intervention.' },
            { id: 'maintenance-reported-date', question: 'Quelle date choisir pour une intervention signalée en retard ?', answer: 'Renseignez la date réelle à laquelle le problème a été constaté. Elle peut être antérieure au jour de saisie. L’enregistrement technique et l’utilisateur connecté sont conservés séparément.' },
            { id: 'maintenance-completed-date', question: 'Puis-je terminer aujourd’hui une intervention achevée hier ?', answer: 'Oui. En choisissant « Terminer », indiquez la date réelle d’achèvement. Elle doit être comprise entre la date du signalement et aujourd’hui. La date d’enregistrement et l’auteur de la transition restent conservés dans l’audit.' },
            { id: 'maintenance-scope', question: 'Comment filtrer les interventions par ville ?', answer: 'Sélectionnez la ville active dans le sélecteur en haut de la page. La liste doit afficher uniquement les interventions de cette ville et de votre périmètre autorisé.' },
            { id: 'maintenance-status', question: 'Comment faire avancer une intervention ?', answer: 'Utilisez l’action de la ligne correspondant à l’étape suivante, par exemple démarrer ou terminer, puis vérifiez le nouvel état. Les actions disponibles dépendent de l’état courant.' },
            { id: 'maintenance-edit', question: 'Comment corriger les détails d’une intervention ?', answer: 'Utilisez « Modifier » sur la ligne concernée, mettez à jour les informations proposées puis enregistrez. Une intervention appartient au bien et à la ville sélectionnés.' },
            { id: 'maintenance-empty', question: 'Pourquoi la liste est-elle vide ?', answer: 'Vérifiez la ville active, puis actualisez la liste. Une intervention créée pour une autre ville ne doit pas apparaître ici.' },
        ],
    },
    '/app/operations': {
        title: 'Opérations',
        description: 'Consultez les affectations du personnel dans les villes qui vous sont attribuées.',
        questions: [
            { id: 'operations-scope', question: 'Comment choisir la ville affichée ?', answer: 'Utilisez la ville active ou le filtre de ville présent sur la page. La liste doit se limiter aux affectations de cette ville et aux villes attribuées à votre compte.' },
            { id: 'operations-assign', question: 'Comment affecter une personne à un bien ?', answer: 'Ouvrez ou créez une affectation, sélectionnez la personne et le bien de la ville concernée, puis renseignez le rôle et les dates demandés avant validation.' },
            { id: 'operations-multiple', question: 'Une personne peut-elle travailler sur plusieurs biens ?', answer: 'Oui, une personne peut avoir plusieurs affectations. Vérifiez le bien et la ville affichés sur chaque affectation pour distinguer clairement ses missions.' },
            { id: 'operations-access', question: 'Pourquoi une action de gestion est-elle absente ?', answer: 'Les droits d’affectation dépendent de votre rôle et de la délégation configurée par l’organisation. Les personnes et les affectations restent isolées par ville.' },
        ],
    },
    '/app/patrimoine': {
        title: 'Patrimoine',
        description: 'Les questions affichées suivent le niveau du patrimoine actuellement ouvert et votre rôle actif.',
        questions: [
            { id: 'property-navigate', question: 'Comment parcourir le patrimoine ?', answer: 'Commencez par la ville, puis ouvrez une parcelle, un bâtiment et une unité pour descendre dans la hiérarchie. Utilisez le fil d’Ariane de la page pour revenir au niveau précédent.' },
            { id: 'property-adminimmo-actions', question: 'Quelles actions Patrimoine l’ADMIN_IMMOBILIER peut-il effectuer ?', answer: 'Dans son organisation active, il peut créer, modifier et supprimer logiquement les villes, parcelles, bâtiments et unités, changer le statut d’une ville, gérer les niveaux et accès liés aux unités, et publier une unité si la Vitrine est autorisée. Les plafonds de capacité s’appliquent. Vérifiez les baux et l’historique avant une suppression ; les rattachements à une autre organisation sont refusés.', roles: ['admin_immobilier'], keywords: ['ville', 'créer', 'ajouter', 'modifier', 'supprimer', 'parcelle', 'bâtiment', 'unité', 'vitrine', 'admin immobilier'] },
            { id: 'property-create', question: 'Comment ajouter un élément immobilier ?', answer: 'Choisissez le niveau concerné et le parent demandé. Si votre rôle permet la création à ce niveau, le bouton d’ajout est visible. Complétez les champs obligatoires puis enregistrez.' },
            { id: 'property-city-admin-assignment', question: 'Comment ajouter un ADMIN_VILLE à une ville ?', answer: 'Dans la liste des villes, ouvrez les actions de la ligne concernée puis « Ajouter un ADMIN_VILLE ». Le compte créé est automatiquement rattaché à cette ville ; vérifiez les coordonnées avant l’envoi de l’invitation.', roles: ['admin_immobilier'], keywords: ['ville', 'admin-ville', 'ajouter', 'affecter', 'inviter'] },
            { id: 'property-patron-scope', question: 'Que puis-je faire dans Patrimoine avec le rôle PATRON ?', answer: 'Le PATRON consulte la structure immobilière et ses indicateurs. Les boutons de création, modification et suppression ne sont pas disponibles pour ce rôle ; les opérations d’entretien du patrimoine sont déléguées aux administrateurs concernés.', roles: ['patron'], keywords: ['droits', 'lecture', 'patron', 'accès'] },
            { id: 'property-adminville-scope', question: 'Que puis-je gérer comme ADMIN_VILLE ?', answer: 'Vous gérez les parcelles, bâtiments et unités des villes qui vous sont attribuées. Vous ne créez ni ne modifiez les villes et vous ne voyez pas les autres villes de l’organisation. Les actions locatives restent accessibles dans votre périmètre de ville.', roles: ['admin_ville'], keywords: ['droits', 'ville', 'admin ville', 'périmètre'] },
            { id: 'property-nested-spaces', question: 'Comment décrire une maison avec plusieurs niveaux ou espaces imbriqués ?', answer: 'Dans l’onglet Unités, sélectionnez le bâtiment puis utilisez « Gérer les niveaux » pour créer des niveaux avec référence, libellé, ordre et description. Associez ensuite chaque unité à son niveau. Le champ « Espace parent » représente les subdivisions physiques. Un bail actif sur un espace bloque un bail incompatible sur son parent ou ses sous-espaces.' },
            { id: 'property-structure-change', question: 'Puis-je restructurer un espace déjà loué ?', answer: 'Non. Le rattachement parent ne peut pas être modifié lorsque l’espace, son parent ou l’un de ses sous-espaces porte un bail actif. Clôturez ou transférez d’abord les baux concernés afin de préserver les contrats et l’historique financier.' },
            { id: 'property-house-entire', question: 'Comment louer une maison entière ?', answer: 'Créez une unité qui représente la maison entière et louez-la comme une seule unité. Vous n’avez pas besoin de créer une unité locative pour chaque pièce. Si vous avez déjà créé des subdivisions, le bail sur l’espace parent bloque leurs locations séparées.' },
            { id: 'property-floor-split', question: 'Comment louer séparément deux logements du même étage ?', answer: 'Créez chaque logement comme une unité indépendante, donnez-leur le même libellé de niveau puis attribuez des références distinctes. Les unités sœurs peuvent être louées indépendamment.' },
            { id: 'property-mixed-use', question: 'Puis-je gérer des commerces et des logements dans le même bâtiment ?', answer: 'Oui. Le type se choisit pour chaque unité : boutique, bureau, appartement, chambre, entrepôt ou autre. Le bâtiment peut donc regrouper plusieurs usages.' },
            { id: 'property-access', question: 'Comment représenter une entrée commune ?', answer: 'Pour l’entrée principale d’un terrain, ouvrez l’onglet Bâtiments, filtrez sur la parcelle puis choisissez « Gérer les entrées de la parcelle ». Pour une porte propre au bâtiment, ouvrez l’onglet Unités et choisissez « Gérer les accès ». Les accès de bâtiment peuvent desservir plusieurs unités. Désactiver un accès ne libère pas les logements.' },
            { id: 'property-composite-lease', question: 'Comment réunir plusieurs espaces dans un même contrat ?', answer: 'Lors de la création du bail, choisissez une unité principale, puis cochez les espaces supplémentaires. Les espaces doivent se trouver dans la même ville; le loyer saisi représente le montant global du contrat. Un bail actif incompatible sur l’un des espaces ou ses subdivisions sera refusé.' },
            { id: 'property-household', question: 'Comment enregistrer une famille ou une colocation ?', answer: 'Créez une fiche pour chaque personne. Lors de la création du bail, sélectionnez les autres personnes puis indiquez si chacune est cotitulaire ou occupant. Le titulaire principal reste le responsable financier du bail; les dates d’entrée et de sortie des autres personnes sont conservées séparément.' },
            { id: 'property-partial-occupancy', question: 'Comment savoir si un bâtiment est partiellement occupé ?', answer: 'Consultez les unités et leurs baux actifs. L’occupation se calcule à l’échelle des espaces louables : un bâtiment peut contenir en même temps des espaces occupés et d’autres disponibles. Les subdivisions actives héritent du conflit avec leur espace parent.' },
            { id: 'property-usage-change', question: 'Comment transformer un ancien local commercial en logement ?', answer: 'Modifiez le type d’unité lorsqu’aucun bail actif ne dépend de cet espace, puis vérifiez son libellé, son niveau et ses conditions locatives. Les contrats et paiements passés restent liés à l’espace et ne sont pas réécrits.' },
            { id: 'property-restructure-history', question: 'Comment restructurer un bâtiment sans perdre l’historique des contrats ?', answer: 'Les relations parent-enfant ne peuvent pas être changées lorsqu’un bail actif concerne les espaces touchés. Terminez ou transférez d’abord les contrats concernés. Les anciens contrats et paiements restent conservés ; consignez les changements d’occupation dans l’historique résidentiel.' },
            { id: 'property-building-access', question: 'Une même porte peut-elle desservir plusieurs unités ?', answer: 'Oui. Dans « Gérer les accès », cochez plusieurs unités pour un même accès commun. Une unité peut aussi être associée à plusieurs accès. La référence et le libellé de l’accès restent distincts des références de logement.' },
            { id: 'property-search', question: 'Comment rechercher une parcelle, un bâtiment ou une unité ?', answer: 'Utilisez le champ de recherche et les filtres disponibles dans l’onglet courant. Si les résultats sont vides, vérifiez le niveau sélectionné et la ville active.' },
            { id: 'property-map', question: 'Comment consulter les repères GPS ?', answer: 'Ouvrez la carte depuis l’action associée aux parcelles géolocalisées. Les marqueurs regroupés peuvent être sélectionnés ; utilisez les commandes de zoom et de déplacement de la carte.' },
            { id: 'property-relations', question: 'Pourquoi un élément n’apparaît-il pas ?', answer: 'Vérifiez qu’il appartient à la ville et au niveau actuellement ouverts. L’Administrateur de ville ne voit que son périmètre attribué ; les autres organisations ne sont jamais incluses.' },
            { id: 'property-capacity-limit', question: 'Pourquoi la création d’une ressource est-elle refusée ?', answer: 'L’organisation a peut-être atteint un plafond configuré par le SUPER_ADMIN. Les données existantes restent consultables ; seul l’ajout d’éléments supplémentaires de cette catégorie est bloqué. Le PATRON ou l’ADMIN_IMMOBILIER peut consulter l’usage dans Paramètres → Organisation et demander un ajustement au SUPER_ADMIN. L’ADMIN_VILLE ne voit pas les compteurs globaux.', roles: ['patron', 'admin_immobilier', 'admin_ville'], keywords: ['capacité', 'limite', 'plafond', 'création', 'dépassement'] },
        ],
    },
    '/app/personnel': {
        title: 'Personnel',
        description: 'Gérez les membres opérationnels et leurs affectations sur les biens.',
        questions: [
            { id: 'staff-find', question: 'Comment rechercher un membre du personnel ?', answer: 'Saisissez son nom ou son adresse affichée dans la recherche, puis filtrez par ville si le sélecteur est disponible. Le compteur d’affectations résume ses missions enregistrées.' },
            { id: 'staff-create', question: 'Comment ajouter une personne ?', answer: 'Utilisez l’action d’ajout si elle apparaît, saisissez les informations obligatoires puis enregistrez. La personne est rattachée à l’organisation ; ses affectations précisent les villes et biens où elle intervient.' },
            { id: 'staff-assignment', question: 'Comment affecter un membre à plusieurs biens ?', answer: 'Ouvrez les actions du membre et ajoutez une affectation pour chaque bien concerné. Contrôlez la ville de chaque bien : les affectations sont distinctes et ne doivent pas être confondues.' },
            { id: 'staff-access', question: 'Pourquoi le bouton d’ajout est-il absent ?', answer: 'La création de personnes est limitée aux rôles et délégations qui l’autorisent. Demandez au Patron ou à l’Administrateur immobilier de vérifier l’autorisation accordée à votre ville.' },
        ],
    },
    '/app/rapports': {
        title: 'Rapports',
        description: 'Analysez l’activité locative, patrimoniale et financière de votre périmètre.',
        questions: [
            { id: 'reports-period', question: 'Comment choisir la période du rapport ?', answer: 'Sélectionnez la période ou les dates proposées par les filtres. Les indicateurs et graphiques sont recalculés pour le périmètre autorisé et la période choisie.' },
            { id: 'reports-read', question: 'Comment interpréter les montants ?', answer: 'Les montants sont regroupés par devise et par catégorie affichée. Ne comparez pas directement des montants de devises différentes sans conversion explicitement indiquée.' },
            { id: 'reports-date-basis', question: 'Quelle date sert aux rapports financiers ?', answer: 'Les encaissements sont comptés selon la date réelle du paiement; les dépenses selon leur date réelle de dépense. La date d’enregistrement reste conservée séparément pour la traçabilité et n’ajoute pas une seconde écriture aux totaux.' },
            { id: 'reports-export', question: 'Comment télécharger le rapport ?', answer: 'Après avoir choisi la période, utilisez « Exporter en PDF » si ce bouton apparaît. Le fichier reprend les données visibles et autorisées pour votre rôle.' },
            { id: 'reports-empty', question: 'Pourquoi les données sont-elles absentes ?', answer: 'Vérifiez la période et la ville active. Le rapport ne montre que les données enregistrées dans votre périmètre ; rechargez la page si une erreur est signalée.' },
        ],
    },
    '/app/administration': {
        title: 'Administration',
        description: 'Gérez les membres et les accès délégués disponibles dans votre espace.',
        questions: [
            { id: 'admin-team', question: 'Comment retrouver ou inviter un membre ?', answer: 'Recherchez un membre dans la liste. Si vous êtes autorisé à inviter, ouvrez « Inviter un membre », indiquez les informations demandées et validez l’invitation.' , roles: ['patron'] },
            { id: 'admin-edit', question: 'Comment modifier ou suspendre un membre ?', answer: 'Ouvrez les actions du membre concerné. Modifiez les informations dans le formulaire ou choisissez la suspension, indiquez sa durée et son motif, puis confirmez.' , roles: ['patron'] },
            { id: 'admin-city-access', question: 'Comment attribuer les villes à un Administrateur de ville ?', answer: 'Depuis les actions du membre, ouvrez la gestion de ses villes, sélectionnez uniquement les villes dont il doit avoir la charge, puis enregistrez. Ses listes seront bornées à ces villes.' , roles: ['patron'] },
            { id: 'admin-delegation', question: 'Comment autoriser un Administrateur de ville à ajouter du personnel ?', answer: 'Ouvrez la gestion des accès délégués et activez l’autorisation de création pour l’Administrateur de ville concerné. Il verra ensuite l’action d’ajout dans son espace ; l’API vérifie également cette permission.' , roles: ['admin_immobilier'] },
            { id: 'admin-member-capacity', question: 'Que faire si le plafond de membres est atteint ?', answer: 'Le nombre affiché correspond aux comptes actifs rattachés à l’organisation, tous rôles métier confondus. Consultez le plafond dans Paramètres → Organisation, puis demandez au SUPER_ADMIN de revoir la capacité. Aucun compte existant n’est supprimé.', roles: ['patron', 'admin_immobilier'], keywords: ['membre', 'compte', 'utilisateur', 'capacité', 'limite'] },
            { id: 'admin-readonly', question: 'Pourquoi certaines commandes ne sont-elles pas disponibles ?', answer: 'Cette page ne montre que les opérations permises par votre rôle et la délégation active. Une commande absente nécessite une autorisation du responsable concerné.' },
        ],
    },
    '/app/organisation': {
        title: 'Organisation', description: 'Consultez et mettez à jour les informations générales et le logo de l’organisation.',
        questions: [
            { id: 'organization-profile', question: 'Comment modifier les informations de l’organisation ?', answer: 'Dans la section Identité légale, modifiez les champs autorisés puis sélectionnez « Enregistrer ». Les informations non modifiables sont affichées en lecture seule.' },
            { id: 'organization-logo', question: 'Comment mettre à jour le logo ?', answer: 'Choisissez un fichier avec le contrôle de logo, vérifiez son aperçu puis enregistrez le logo. Si l’enregistrement échoue, vérifiez le format et la taille acceptés.' },
            { id: 'organization-access', question: 'Pourquoi ne puis-je pas modifier ces informations ?', answer: 'Les opérations disponibles dépendent de votre rôle et des champs modifiables dans cette page. Seul un rôle autorisé peut enregistrer les changements.' },
            { id: 'organization-capacity-limit', question: 'Que faire si une création est refusée pour capacité atteinte ?', answer: 'Vérifiez la section « Offre et capacités » dans Paramètres → Organisation. Si le plafond est atteint, demandez au SUPER_ADMIN de revoir la configuration. Une réduction de plafond ne supprime pas les données déjà enregistrées.', roles: ['patron', 'admin_immobilier'], keywords: ['offre', 'capacité', 'limite', 'plafond', 'atteint'] },
        ],
    },
    '/app/vitrine': {
        title: 'Vitrine',
        description: 'Gérez les annonces immobilières et les réglages publics disponibles pour votre rôle.',
        questions: [
            { id: 'showcase-entitlement', question: 'Pourquoi le menu Vitrine est-il absent ?', answer: 'Le SUPER_ADMIN doit d’abord autoriser la fonctionnalité Vitrine dans la fiche de votre organisation. Après activation, le menu apparaît pour les rôles PATRON et ADMIN_IMMOBILIER ; l’ADMIN_VILLE ne dispose pas de ce menu.' },
            { id: 'showcase-listings', question: 'Comment retrouver une annonce ?', answer: 'Recherchez par titre ou utilisez le filtre de statut. Les annonces publiées et les brouillons sont identifiés séparément.' },
            { id: 'showcase-edit', question: 'Comment publier ou modifier une annonce ?', answer: 'Ouvrez l’action de modification de l’annonce, complétez les informations demandées puis enregistrez. La publication d’une unité occupée par un bail actif peut être refusée par la règle métier.' },
            { id: 'showcase-photos', question: 'Comment gérer les photos ?', answer: 'Ouvrez la gestion des photos depuis l’annonce concernée, ajoutez ou retirez les images proposées puis enregistrez les changements.' },
            { id: 'showcase-settings', question: 'Qui peut modifier les réglages publics ?', answer: 'Le Patron dispose des réglages publics de l’organisation. Dans une vue limitée à la gestion des annonces, seules les actions affichées sont disponibles.' },
        ],
    },
    '/settings': {
        title: 'Paramètres',
        description: 'Configurez vos préférences personnelles et les options accessibles à votre compte.',
        questions: [
            { id: 'settings-theme', question: 'Comment choisir le thème et la couleur ?', answer: 'Dans la section Apparence, choisissez le thème (clair, sombre ou système) et l’une des couleurs de palette. Le choix est enregistré dans vos préférences et s’applique aux composants compatibles.' },
            { id: 'settings-date-format', question: 'Comment changer le format des dates ?', answer: 'Choisissez JJ/MM/AAAA, MM/JJ/AAAA ou AAAA-MM-JJ dans « Format de date ». Le changement est appliqué aux dates formatées par l’application ; il ne modifie pas les dates enregistrées ni les données déjà exportées.', keywords: ['date', 'format', 'jour', 'mois', 'année'] },
            { id: 'settings-compact-tables', question: 'À quoi sert l’affichage compact des tableaux ?', answer: 'Activez « Tableaux compacts » pour réduire la hauteur des lignes et voir davantage d’éléments à l’écran. Désactivez-le pour retrouver l’espacement normal. Ce réglage change la présentation, pas le nombre total de résultats ni les permissions.', keywords: ['tableau', 'lignes', 'densité', 'compact', 'espacement'] },
            { id: 'settings-page-size', question: 'Comment afficher plus ou moins de lignes dans les listes ?', answer: 'Choisissez le nombre de lignes par page dans « Lignes par page ». Les listes paginées utilisent ce choix lorsqu’elles prennent en charge la préférence ; utilisez ensuite la pagination pour parcourir les autres résultats.', keywords: ['pagination', '10', '20', '50', '100', 'résultats'] },
            { id: 'settings-motion', question: 'Que change la réduction des animations ?', answer: 'Activez cette option pour limiter les transitions et animations de l’interface. Les menus, formulaires et actions restent disponibles ; seul leur mouvement visuel est réduit.', keywords: ['animation', 'transition', 'mouvement', 'accessibilité'] },
            { id: 'settings-shortcut', question: 'Comment ajouter Soft-IMMO à l’écran d’accueil ou au bureau ?', answer: 'Dans « Accès rapide », activez « Afficher l’option d’installation », puis choisissez « Installer ou créer un raccourci ». Si l’installation directe n’est pas disponible, suivez les étapes proposées pour votre appareil. Sur Android/Chrome, utilisez le menu ⋮ ; sur iPhone/iPad, Partager puis « Sur l’écran d’accueil » ; sur ordinateur, l’icône ou le menu d’installation du navigateur. Une adresse locale en HTTP (par exemple une adresse IP du réseau) ne permet pas l’installation PWA : Chrome exige HTTPS, sauf pour localhost. En production, le site doit être servi en HTTPS. Le réglage est enregistré pour votre compte, mais chaque installation reste propre à l’appareil et au navigateur utilisés.', keywords: ['raccourci', 'installer', 'installation', 'PWA', 'écran d’accueil', 'bureau', 'téléphone', 'HTTP', 'HTTPS'] },
            { id: 'settings-notifications', question: 'Comment fonctionnent les notifications par e-mail et dans l’application ?', answer: 'Les deux réglages sont indépendants : l’e-mail concerne les alertes importantes envoyées par courrier, tandis que les notifications dans l’application contrôlent les messages toast temporaires de confirmation et d’état. Désactiver les toasts ne bloque pas l’enregistrement des actions.', keywords: ['email', 'e-mail', 'toast', 'message', 'confirmation', 'alerte'] },
            { id: 'settings-navigation', question: 'Comment garder plus de place pour le contenu ?', answer: 'Activez « Menu latéral replié au démarrage » pour ouvrir l’application avec le menu réduit sur ordinateur. Vous pouvez toujours le déployer depuis son bouton. Sur petit écran, le menu suit le comportement mobile et cette préférence ne change pas les accès.', keywords: ['menu', 'sidebar', 'navigation', 'écran', 'mobile'] },
            { id: 'settings-account', question: 'Comment modifier et enregistrer mes préférences ?', answer: 'Les réglages sont enregistrés individuellement dès que vous changez un contrôle. Un toast confirme le réglage appliqué ; en cas d’échec de synchronisation, l’application affiche l’erreur et rétablit la valeur précédente. Corrigez le problème puis réessayez.' },
            { id: 'settings-currency', question: 'Pourquoi certains paramètres d’organisation ne sont-ils pas visibles ?', answer: 'Les paramètres financiers et ceux de l’organisation dépendent de votre rôle et de l’activation de la fonctionnalité. Consultez la section Organisation si elle est proposée.' },
        ],
    },
    '/settings/organisation': {
        title: 'Paramètres de l’organisation',
        description: 'Consultez les réglages financiers et les options organisationnelles accessibles.',
        questions: [
            { id: 'org-settings-currency', question: 'Comment choisir la devise de référence ?', answer: 'Sélectionnez USD ou CDF puis enregistrez le réglage. Les montants d’origine restent distincts ; le taux de change sert aux rapports qui appliquent cette conversion.' },
            { id: 'org-settings-rate', question: 'Comment mettre à jour un taux de change ?', answer: 'Saisissez le taux demandé dans la section de l’organisation ou de la ville concernée, puis enregistrez. L’historique indique la date d’effet et une dérogation locale lorsqu’elle existe.' },
            { id: 'org-settings-access', question: 'Pourquoi cette section n’est-elle pas disponible ?', answer: 'L’accès dépend de votre rôle et de la configuration financière de l’organisation. Si le lien n’est pas affiché, demandez au Patron ou à l’Administrateur immobilier de vérifier les paramètres.' },
            { id: 'org-settings-capacity', question: 'Comment consulter les capacités de l’organisation ?', answer: 'Le PATRON et l’ADMIN_IMMOBILIER voient les plafonds et l’usage actuel des villes, parcelles, bâtiments, unités et membres actifs. « Sans limite » signifie qu’aucun plafond n’a été fixé. Ces réglages sont en lecture seule ; contactez le SUPER_ADMIN pour demander une modification. Il n’existe pas de paiement ni de commande en ligne.', roles: ['patron', 'admin_immobilier'], keywords: ['capacité', 'offre', 'limite', 'usage', 'sans limite', 'SUPER_ADMIN'] },
            { id: 'org-settings-capacity-over', question: 'Que se passe-t-il après une réduction sous l’usage courant ?', answer: 'Le dépassement est affiché sans cacher ni supprimer les éléments existants. Les consultations et modifications ordinaires restent possibles, mais les nouvelles créations de cette ressource sont bloquées jusqu’à ce que le plafond soit relevé ou que l’usage redescende.', roles: ['patron', 'admin_immobilier'], keywords: ['réduction', 'dépassement', 'données', 'blocage'] },
        ],
    },
    '/settings/manuel': {
        title: 'Manuel d’utilisation',
        description: 'Ouvrez le guide correspondant au rôle actif et parcourez ses procédures.',
        questions: [
            { id: 'manual-role', question: 'Pourquoi le manuel dépend-il de mon rôle ?', answer: 'Le guide présente les menus, autorisations et procédures accessibles dans votre espace. Si votre rôle actif change, le contenu proposé suit ce rôle.' , keywords: ['rôle', 'permissions', 'menu', 'accès'] },
            { id: 'manual-read', question: 'Comment lire ou télécharger le manuel ?', answer: 'Sélectionnez « Lire le manuel » pour ouvrir le document dans la page et le faire défiler. Utilisez « Télécharger le PDF » pour le conserver hors ligne. Sur téléphone, faites défiler avec le doigt et utilisez le zoom du lecteur si nécessaire.' , keywords: ['pdf', 'lecture', 'télécharger', 'mobile', 'zoom', 'défilement'] },
            { id: 'manual-sections', question: 'Comment trouver une procédure dans le guide ?', answer: 'Utilisez l’arbre des sections pour choisir un écran ou une action. Les étapes décrivent le parcours, les champs à vérifier et les résultats attendus ; les encadrés signalent les erreurs courantes et les actions réservées à certains rôles.' , keywords: ['arbre', 'navigation', 'procédure', 'étapes', 'erreur'] },
        ],
    },
    '/profile': {
        title: 'Mon profil',
        description: 'Consultez et mettez à jour les informations de votre compte.',
        questions: [
            { id: 'profile-edit', question: 'Comment modifier mon profil ?', answer: 'Cliquez sur « Modifier », mettez à jour les champs proposés et enregistrez. Vérifiez le message de confirmation avant de quitter la page.' },
            { id: 'profile-photo', question: 'Comment changer ma photo ?', answer: 'Sélectionnez « Modifier », choisissez ou recadrez une photo avec le contrôle du profil, puis enregistrez. La nouvelle photo apparaît immédiatement dans le menu latéral, sans actualiser la page. En cas d’échec, vérifiez le format et la taille indiqués par l’interface.' },
            { id: 'profile-security', question: 'Où modifier mon mot de passe ?', answer: 'Utilisez le parcours « Mot de passe oublié » depuis la page de connexion si aucun formulaire de changement n’est proposé dans le profil.' },
        ],
    },
};

/** Procédures liées aux libellés et parcours réels des écrans, en complément des réponses FAQ. */
const actionGuides: Record<string, HelpQuestion['guide']> = {
    'login-access': { location: 'Page Connexion → formulaire Se connecter', steps: ['Saisissez l’adresse e-mail de votre compte.', 'Saisissez votre mot de passe.', 'Sélectionnez Se connecter et choisissez l’organisation si plusieurs espaces vous sont attribués.'], result: 'L’application ouvre l’espace autorisé pour le rôle actif.' },
    'login-theme': { location: 'Page Connexion → sélecteur de thème de la carte', steps: ['Choisissez clair ou sombre dans le sélecteur visible sur la carte.', 'Vérifiez que le contraste de la page vous convient.'], result: 'Le choix est conservé sur cet appareil.' },
    'login-password': { location: 'Page Connexion → Mot de passe oublié ?', steps: ['Ouvrez Mot de passe oublié ?', 'Saisissez l’adresse e-mail associée au compte et envoyez la demande.', 'Consultez votre boîte de réception et suivez le lien reçu.'], result: 'Pour protéger les comptes, la confirmation ne révèle pas si l’adresse est enregistrée.' },
    'forgot-request': { location: 'Page Mot de passe oublié → formulaire de demande', steps: ['Saisissez votre adresse e-mail.', 'Envoyez la demande.', 'Consultez la boîte de réception et les courriers indésirables.'], result: 'Si un compte correspondant existe, un lien temporaire est envoyé.' },
    'reset-form': { location: 'Lien reçu par e-mail → formulaire Nouveau mot de passe', steps: ['Ouvrez le lien temporaire reçu.', 'Saisissez le nouveau mot de passe selon les règles affichées.', 'Confirmez-le puis validez le formulaire.'], result: 'Revenez à Connexion et utilisez le nouveau mot de passe.' },
    'denied-next': { location: 'Page Accès refusé → bouton Accueil', steps: ['Sélectionnez Accueil pour revenir à la destination de votre rôle.', 'Vérifiez l’organisation et le rôle actif dans le menu du compte.', 'Si un accès manque, demandez à l’administrateur de vérifier votre rôle ou vos villes attribuées.'], result: 'La navigation reste limitée aux pages autorisées pour le compte.' },
    'org-find': { location: 'Administration plateforme → Organisations', steps: ['Saisissez un nom ou un code dans la recherche, ou choisissez un statut.', 'Repérez l’organisation dans la liste puis ouvrez sa ligne ou son menu Actions.', 'Choisissez la consultation, la modification ou l’opération souhaitée.'], result: 'La fiche ouverte doit afficher le nom et le code de l’organisation recherchée.' },
    'org-create': { location: 'Administration plateforme → Organisations → Nouvelle organisation', steps: ['Sélectionnez « Nouvelle organisation ».', 'Complétez les étapes Identité, Capacités, Compte PATRON et Logo ; le logo est facultatif.', 'Utilisez Continuer pour avancer et Vérifiez les informations avant la confirmation finale.'], result: 'La nouvelle organisation apparaît dans la liste avec son PATRON associé.' },
    'org-suspend': { location: 'Administration plateforme → Organisations → menu Actions de la ligne', steps: ['Ouvrez les actions de l’organisation concernée.', 'Choisissez Suspendre, sélectionnez une durée et indiquez le motif demandé.', 'Relisez le nom de l’organisation et confirmez. Pour réactiver, rouvrez ses actions et choisissez Réactiver.'], result: 'Le statut de l’organisation change dans la liste ; vérifiez qu’il correspond à l’opération confirmée.' },
    'org-create-steps': { location: 'Page Nouvelle organisation — indicateur d’étapes en haut du formulaire', steps: ['Étape Identité : saisissez les informations et coordonnées de l’organisation.', 'Étape Capacités : fixez les plafonds de villes, parcelles, bâtiments, unités et membres, ou gardez Sans limite.', 'Étape Compte PATRON : renseignez le compte initial.', 'Étape Logo : ajoutez un logo si nécessaire, puis vérifiez le récapitulatif et confirmez.'], result: 'L’étape active est indiquée en haut ; les champs déjà saisis restent présents lorsque vous revenez en arrière.' },
    'org-create-limits': { location: 'Nouvelle organisation → étape Capacités', steps: ['Pour chaque ressource, laissez Sans limite si aucun plafond ne doit s’appliquer.', 'Sinon désactivez Sans limite et saisissez une limite positive.', 'Relisez chaque valeur avant de poursuivre vers Compte PATRON.'], result: 'Les plafonds sont enregistrés sur l’organisation et limitent les nouvelles créations lorsqu’ils sont atteints.' },
    'org-create-patron': { location: 'Nouvelle organisation → étape Compte PATRON', steps: ['Saisissez le nom et les coordonnées du PATRON désigné.', 'Vérifiez l’adresse e-mail : elle sert à envoyer le lien de configuration du mot de passe.', 'Terminez les étapes restantes et confirmez la création.'], result: 'Le compte PATRON est rattaché à la nouvelle organisation.' },
    'org-edit-steps': { location: 'Administration plateforme → Organisations → menu Actions → Modifier', steps: ['Ouvrez Modifier sur la ligne de l’organisation.', 'Parcourez Informations générales, Capacités et options, puis Logo.', 'Utilisez Continuer pour avancer, Étape précédente pour revenir, puis confirmez à la dernière étape.'], result: 'Les valeurs sont préremplies ; contrôlez les changements avant de les enregistrer.' },
    'org-edit-showcase': { location: 'Modifier une organisation → Capacités et options', steps: ['Ouvrez l’organisation concernée depuis la liste.', 'À l’étape Capacités et options, activez ou désactivez l’autorisation Vitrine.', 'Terminez le parcours et confirmez la modification.'], result: 'Après activation, le menu Vitrine est accessible au PATRON et à l’ADMIN_IMMOBILIER de cette organisation.' },
    'publications-draft': { location: 'Administration plateforme → Publications → Nouvelle publication', steps: ['Ouvrez le formulaire de nouvelle publication.', 'Renseignez le titre et le contenu, puis choisissez la catégorie et le public cible.', 'Ajoutez des images si nécessaire, vérifiez l’aperçu et enregistrez le brouillon.'], result: 'Le brouillon apparaît dans la liste des publications et peut être repris avant diffusion.' },
    'publications-audience': { location: 'Nouvelle publication → champ Public cible', steps: ['Choisissez Tous les utilisateurs métier actifs, un rôle ou une organisation selon le public visé.', 'Relisez la sélection avant d’enregistrer : les comptes SUPER_ADMIN ne sont pas destinataires.', 'Enregistrez le brouillon puis vérifiez son public dans la fiche.'], result: 'La publication sera visible uniquement par les comptes métier correspondant au ciblage.' },
    'publications-schedule': { location: 'Nouvelle publication → Publier à partir du / Fin de diffusion', steps: ['Indiquez une date de début future pour programmer la diffusion ; laissez-la immédiate pour publier sans attente.', 'Ajoutez une date de fin uniquement si la publication doit expirer.', 'Vérifiez que la fin est postérieure au début, puis enregistrez et publiez.'], result: 'Les dates choisies déterminent la période de visibilité de l’annonce.' },
    'publications-publish': { location: 'Administration plateforme → Publications → menu Actions de la publication', steps: ['Ouvrez le brouillon et vérifiez contenu, public cible et dates.', 'Choisissez Publier maintenant ou la programmation prévue.', 'Pour une annonce déjà diffusée, rouvrez ses actions et choisissez Retirer ou Archiver selon le besoin.'], result: 'Vérifiez le statut affiché dans la liste après chaque opération.' },
    'users-search': { location: 'Administration plateforme → Utilisateurs', steps: ['Recherchez par nom ou adresse e-mail.', 'Réduisez la liste avec les filtres de statut ou de rôle disponibles.', 'Ouvrez le menu Actions de la ligne voulue pour consulter les opérations autorisées.'], result: 'Vérifiez l’identité et l’adresse du compte avant toute modification.' },
    'users-suspend': { location: 'Administration plateforme → Utilisateurs → menu Actions', steps: ['Ouvrez les actions du compte ciblé et sélectionnez Suspendre.', 'Choisissez la durée, indiquez le motif si demandé, puis relisez le compte concerné.', 'Confirmez. Pour rendre l’accès, choisissez Réactiver dans le même menu.'], result: 'Le statut du compte doit refléter la suspension ou la réactivation.' },
    'expenses-create': { location: 'Trésorerie → Gestion des dépenses → Enregistrer une dépense', steps: ['Ouvrez le formulaire d’enregistrement.', 'Sélectionnez les références disponibles, puis indiquez description, catégorie, montant, devise et date réelle.', 'Vérifiez le montant et la devise avant de confirmer.'], result: 'La dépense confirmée apparaît dans l’historique avec son statut et sa provenance.' },
    'expenses-search': { location: 'Trésorerie → Gestion des dépenses → recherche et filtres', steps: ['Saisissez un terme dans la recherche.', 'Appliquez les filtres disponibles, par exemple catégorie ou période.', 'Retirez les filtres ou effacez la recherche pour réafficher toute la liste autorisée.'], result: 'Le tableau correspond aux critères actifs ; le périmètre de votre rôle reste appliqué.' },
    'expenses-cancel': { location: 'Gestion des dépenses → ligne de dépense → menu Actions', steps: ['Retrouvez la dépense et vérifiez son montant, sa devise et sa date.', 'Choisissez Annuler uniquement si l’action est disponible et que l’écriture doit réellement être annulée.', 'Lisez le dialogue de confirmation et fournissez le motif demandé.'], result: 'La dépense reste traçable dans l’historique avec son état annulé.' },
    'rents-payment': { location: 'Loyers → échéance concernée → Enregistrer un paiement', steps: ['Ouvrez l’échéance du locataire concerné.', 'Sélectionnez Enregistrer un paiement et saisissez montant, devise, date réelle et mode de paiement.', 'Vérifiez que le paiement correspond bien à cette échéance puis confirmez.'], result: 'Le paiement apparaît dans le détail de l’échéance ; un paiement validé est une trace comptable.' },
    'rents-payment-correction': { location: 'Loyers → échéance → détail du paiement', steps: ['Ouvrez le paiement et confirmez l’erreur à corriger.', 'Si vos droits le permettent, utilisez Annuler et indiquez le motif.', 'Enregistrez un nouveau paiement avec les informations réelles correctes.'], result: 'L’écriture d’origine et son annulation restent visibles ; le nouveau paiement est distinct.' },
    'rents-calendar': { location: 'Loyers → calendrier de la page', steps: ['Choisissez une date marquée dans le calendrier.', 'Consultez les échéances ou paiements associés à cette date.', 'Effacez la sélection de date pour revenir à la liste générale.'], result: 'La liste est filtrée par la date sélectionnée.' },
    'rents-filter': { location: 'Loyers → recherche et filtres au-dessus du tableau', steps: ['Choisissez le statut des échéances à consulter.', 'Ajoutez un terme de recherche ou une date si nécessaire.', 'Effacez les critères pour retrouver toutes les échéances autorisées.'], result: 'Les impayés et retards sont calculés à partir des échéances et paiements enregistrés.' },
    'tenants-find': { location: 'Locataires → recherche et filtres au-dessus de la liste', steps: ['Recherchez le locataire par nom ou information affichée.', 'Appliquez les filtres utiles : type, occupation, plusieurs logements, échéance ou mouvements.', 'Ouvrez la fiche du résultat pour accéder aux baux et à l’historique résidentiel.'], result: 'La liste respecte l’organisation ou les villes auxquelles votre rôle donne accès.' },
    'tenants-lease': { location: 'Locataires → fiche du locataire → Baux', steps: ['Ouvrez le locataire concerné puis sa section Baux.', 'Choisissez l’action de création ou de modification du bail si elle est disponible dans votre rôle.', 'Sélectionnez les unités, renseignez les dates et conditions, puis vérifiez les personnes associées avant validation.'], result: 'Le bail apparaît dans la fiche du locataire avec son statut ; l’opération est réalisée par l’ADMIN_VILLE de la ville.' },
    'tenants-movements': { location: 'Locataires → fiche du locataire → Historique résidentiel', steps: ['Ouvrez la fiche de la personne et consultez les présences ou mouvements.', 'Choisissez l’action correspondant au mouvement réel : entrée, départ, transfert ou retour.', 'Renseignez la personne concernée, la date et le motif, puis vérifiez avant de confirmer.'], result: 'Le mouvement est ajouté à l’historique avec sa date et son auteur.' },
    'tenants-transfer': { location: 'Locataires → fiche du locataire → bail / historique résidentiel', steps: ['Vérifiez le bail actif et l’unité de destination dans la même ville.', 'Préparez le bail de destination avec ses dates et conditions.', 'Enregistrez ensuite le mouvement de transfert avec sa date réelle et son motif.'], result: 'Les paiements passés restent attachés au bail d’origine ; vérifiez les deux baux et le mouvement.' },
    'tenants-roommate-departure': { location: 'Locataires → fiche du locataire → présence de la personne → Départ', steps: ['Ouvrez la présence de la personne concernée dans le bail actif.', 'Choisissez le départ et indiquez la date réelle ainsi que le motif.', 'Vérifiez que la bonne personne est sélectionnée puis confirmez.'], result: 'Seule la période de cette personne est clôturée ; le bail et les autres occupants ne sont pas supprimés.' },
    'tenants-roommate-return': { location: 'Locataires → fiche du locataire → historique résidentiel → Réintégrer', steps: ['Ouvrez l’ancienne présence de la personne dans le bail actif.', 'Choisissez l’action de réintégration et indiquez la nouvelle date d’entrée.', 'Vérifiez l’absence de chevauchement puis confirmez.'], result: 'Une nouvelle période de présence apparaît ; l’ancienne reste dans l’historique.' },
    'property-navigate': { location: 'Menu latéral → Patrimoine → onglets de la page et fil d’Ariane', steps: ['Dans la vue d’ensemble, choisissez Villes pour ouvrir une ville ou Tous les biens pour rechercher une parcelle ou un bâtiment.', 'Ouvrez une ville, puis une parcelle et un bâtiment pour descendre jusqu’aux unités.', 'Sur chaque fiche, utilisez Aperçu ou Gérer les parcelles / bâtiments / unités ; remontez avec le fil d’Ariane.'], result: 'Le titre, les onglets et le fil d’Ariane indiquent le niveau et le parent actifs.' },
    'property-create': { location: 'Patrimoine → vue d’ensemble ou fiche du parent → onglet Gérer les…', steps: ['Pour une ville, ouvrez Gérer les villes ; pour une parcelle, un bâtiment ou une unité, ouvrez le parent puis son onglet Gérer les parcelles / bâtiments / unités.', 'Choisissez Ajouter une ville, Ajouter une parcelle, Ajouter un bâtiment ou Ajouter une unité selon le niveau.', 'Vérifiez le parent sélectionné, remplissez les champs obligatoires et enregistrez.'], result: 'Le nouvel élément apparaît sous le parent sélectionné. Si le bouton Ajouter est absent, vérifiez votre rôle et vos droits.' },
    'property-adminimmo-actions': { location: 'Menu latéral → Patrimoine → Gérer les villes / parcelles / bâtiments / unités', steps: ['Depuis la vue d’ensemble, choisissez Gérer les villes ou ouvrez un parent pour atteindre son onglet Gérer les parcelles, Gérer les bâtiments ou Gérer les unités.', 'Utilisez le bouton Ajouter pour créer ; utilisez le menu Actions de la ligne pour modifier ou supprimer.', 'Dans Gérer les unités, sélectionnez un bâtiment pour afficher Gérer les niveaux et Gérer les accès.'], result: 'Les actions disponibles dépendent du rôle et des capacités configurées pour l’organisation.' },
    'property-adminville-scope': { location: 'Menu latéral → Patrimoine → ville attribuée → Gérer les parcelles / bâtiments / unités', steps: ['Dans la vue d’ensemble, ouvrez une ville qui vous a été attribuée.', 'Sur la fiche, choisissez Gérer les parcelles ; ouvrez ensuite une parcelle puis Gérer les bâtiments, et un bâtiment puis Gérer les unités.', 'Utilisez le fil d’Ariane pour revenir à la parcelle ou à la ville.'], result: 'Le rôle ADMIN_VILLE ne peut pas créer ni modifier les villes, ni voir les autres villes.' },
    'property-patron-scope': { location: 'Menu latéral → Patrimoine → onglets et fiches en consultation', steps: ['Ouvrez l’onglet Villes et parcourez la structure jusqu’aux unités.', 'Ouvrez les fiches et indicateurs pour consulter les informations.', 'Pour une création ou une correction, transmettez la demande à l’administrateur responsable.'], result: 'Le rôle PATRON consulte le patrimoine ; les commandes de création, modification et suppression ne sont pas proposées.' },
    'property-building-access': { location: 'Patrimoine → Unités → sélectionner le bâtiment → Gérer les accès', steps: ['Choisissez le bâtiment qui contient les unités concernées.', 'Ouvrez Gérer les accès et sélectionnez ou créez l’accès commun.', 'Associez toutes les unités desservies puis enregistrez.'], result: 'Un accès peut desservir plusieurs unités et une unité peut être liée à plusieurs accès.' },
    'property-city-admin-assignment': { location: 'Patrimoine → Villes → actions de la ville → Ajouter un ADMIN_VILLE', steps: ['Ouvrez le menu Actions de la ville concernée.', 'Choisissez Ajouter un ADMIN_VILLE et renseignez le compte demandé.', 'Vérifiez le nom de la ville et les coordonnées du compte avant de confirmer.'], result: 'Le compte ADMIN_VILLE est rattaché à cette ville et son périmètre se limite aux villes attribuées.' },
    'property-nested-spaces': { location: 'Patrimoine → Unités → sélectionner un bâtiment → Gérer les niveaux', steps: ['Choisissez le bâtiment concerné dans le sélecteur.', 'Ouvrez Gérer les niveaux et ajoutez les niveaux avec référence, libellé et ordre.', 'Créez les unités et associez chacune au niveau et, si nécessaire, à un espace parent.'], result: 'Les unités sont affichées sous le bâtiment, au niveau et dans la hiérarchie physique choisis.' },
    'property-access': { location: 'Patrimoine → Bâtiments (entrées de parcelle) ou Unités (accès du bâtiment)', steps: ['Pour une entrée de parcelle, ouvrez Bâtiments, filtrez la parcelle et sélectionnez Gérer les entrées de la parcelle.', 'Pour une porte de bâtiment, ouvrez Unités puis Gérer les accès.', 'Créez ou modifiez l’accès et associez les unités concernées avant d’enregistrer.'], result: 'Vérifiez les unités reliées à l’accès ; désactiver un accès ne libère pas les unités.' },
    'property-search': { location: 'Patrimoine → onglet courant → recherche et sélecteurs de parent', steps: ['Choisissez d’abord le niveau ou le parent voulu.', 'Saisissez une référence ou un libellé dans la recherche.', 'Effacez le texte et les filtres pour revenir à la liste complète de ce niveau.'], result: 'Le résultat doit correspondre à la ville, parcelle ou au bâtiment actuellement sélectionné.' },
    'property-map': { location: 'Patrimoine → Parcelles → action Carte', steps: ['Ouvrez la carte depuis les actions ou la commande de carte des parcelles.', 'Utilisez +/−, la molette ou les gestes tactiles pour zoomer ; faites glisser pour déplacer la carte.', 'Sélectionnez un repère pour afficher les détails et coordonnées.'], result: 'Les repères correspondent aux parcelles localisées de votre périmètre.' },
    'staff-create': { location: 'Menu latéral → Personnel → Ajouter un membre', steps: ['Ouvrez Ajouter un membre.', 'Saisissez les informations du collaborateur et vérifiez l’organisation.', 'Enregistrez, puis ouvrez ses actions pour créer ses affectations aux villes et biens.'], result: 'La fiche est créée ; elle n’est affectée à un bien que lorsqu’une affectation est ajoutée.' },
    'staff-assignment': { location: 'Personnel → actions de la personne → Gérer les affectations', steps: ['Ouvrez les actions de la personne.', 'Ajoutez une affectation et sélectionnez la ville puis le bien concerné.', 'Renseignez le rôle et les dates demandés puis enregistrez. Répétez séparément pour chaque bien.'], result: 'Chaque affectation apparaît séparément avec son bien et sa ville.' },
    'reports-period': { location: 'Menu latéral → Rapports → filtres de période', steps: ['Choisissez une période prédéfinie ou indiquez les dates de début et de fin.', 'Sélectionnez la ville ou le périmètre si la page le propose.', 'Attendez le recalcul avant d’exporter ou de comparer les indicateurs.'], result: 'La période active est visible dans les filtres ; les résultats restent limités à votre rôle.' },
    'reports-export': { location: 'Rapports → filtres puis Exporter en PDF', steps: ['Choisissez la période et les filtres voulus.', 'Vérifiez que les indicateurs affichés correspondent au périmètre attendu.', 'Sélectionnez Exporter en PDF et ouvrez le fichier téléchargé.'], result: 'Le PDF reprend les données visibles et autorisées pour votre rôle.' },
    'maintenance-create': { location: 'Menu latéral → Maintenance → Nouvelle intervention', steps: ['Choisissez le type de bien puis le bien concerné.', 'Saisissez le titre, la description, la priorité et la date prévue.', 'Sélectionnez un responsable si le champ est proposé, puis créez l’intervention.'], result: 'La nouvelle intervention apparaît dans la liste avec son statut initial.' },
    'maintenance-status': { location: 'Maintenance → ligne de l’intervention → menu Actions', steps: ['Vérifiez le bien et l’état actuel de l’intervention.', 'Choisissez l’action correspondant à la transition voulue, par exemple Démarrer ou Terminer.', 'Renseignez les détails demandés et confirmez.'], result: 'Le statut affiché change après la transition acceptée.' },
    'maintenance-edit': { location: 'Maintenance → ligne de l’intervention → Modifier', steps: ['Ouvrez les actions de la bonne intervention.', 'Sélectionnez Modifier et corrigez les champs autorisés.', 'Enregistrez puis vérifiez la ligne mise à jour.'], result: 'L’intervention reste rattachée au même bien et à la même ville.' },
    'operations-assign': { location: 'Opérations → Affectations → Ajouter une affectation', steps: ['Choisissez Ajouter une affectation.', 'Sélectionnez la personne et le bien dans la ville concernée.', 'Indiquez le rôle et les dates demandées, puis enregistrez.'], result: 'L’affectation est visible dans la liste de la ville à laquelle elle appartient.' },
    'admin-team': { location: 'Administration → Équipe de l’organisation → Inviter un membre', steps: ['Vérifiez que le compte à inviter n’existe pas déjà dans l’organisation.', 'Ouvrez Inviter un membre, renseignez ses coordonnées et choisissez le rôle prévu.', 'Pour un ADMIN_VILLE, attribuez précisément ses villes puis confirmez l’invitation.'], result: 'Le nouveau membre apparaît dans l’équipe avec le rôle et les accès attribués.' },
    'admin-edit': { location: 'Administration → Équipe de l’organisation → menu Actions du membre', steps: ['Recherchez le membre et ouvrez son menu Actions.', 'Choisissez Modifier pour corriger les champs autorisés, ou Suspendre pour interrompre son accès.', 'Pour une suspension, indiquez la durée et le motif puis confirmez.'], result: 'Vérifiez le statut et les informations du membre après l’action.' },
    'admin-city-access': { location: 'Administration → Équipe → actions de l’ADMIN_VILLE → Gérer les villes', steps: ['Ouvrez les actions du membre ADMIN_VILLE.', 'Sélectionnez uniquement les villes qu’il doit administrer.', 'Enregistrez puis vérifiez le résumé des villes associées au membre.'], result: 'Ses listes métier sont limitées aux villes attribuées.' },
    'admin-delegation': { location: 'Administration → Accès délégués / permissions de l’ADMIN_VILLE', steps: ['Ouvrez la gestion des accès délégués.', 'Choisissez l’ADMIN_VILLE concerné et activez uniquement la permission de personnel nécessaire.', 'Enregistrez puis demandez-lui de vérifier l’apparition de l’action dans son espace.'], result: 'L’autorisation ne s’applique qu’au membre et au périmètre configurés ; le serveur vérifie également ce droit.' },
    'organization-profile': { location: 'Menu latéral → Organisation → Identité légale', steps: ['Ouvrez la section Identité légale.', 'Modifiez les champs qui ne sont pas en lecture seule.', 'Sélectionnez Enregistrer et vérifiez le message de confirmation.'], result: 'Les informations modifiables affichent les nouvelles valeurs après enregistrement.' },
    'organization-logo': { location: 'Organisation → section Logo', steps: ['Choisissez un fichier image avec le sélecteur de logo.', 'Contrôlez l’aperçu et le recadrage proposés.', 'Enregistrez le logo puis vérifiez qu’il remplace l’image précédente.'], result: 'Le nouveau logo est visible dans la fiche de l’organisation.' },
    'showcase-listings': { location: 'Menu latéral → Vitrine → liste des annonces', steps: ['Recherchez par titre ou filtrez par statut.', 'Ouvrez le menu Actions de l’annonce voulue.', 'Choisissez Modifier pour consulter ou mettre à jour ses informations.'], result: 'Vérifiez le statut Brouillon ou Publiée dans la liste.' },
    'showcase-edit': { location: 'Vitrine → annonce concernée → Modifier', steps: ['Ouvrez l’annonce depuis la liste.', 'Modifiez les informations disponibles et contrôlez les photos associées.', 'Enregistrez, puis utilisez l’action de publication si la diffusion est souhaitée.'], result: 'Une unité avec un bail actif peut être empêchée d’être publiée.' },
    'showcase-photos': { location: 'Vitrine → annonce concernée → Photos', steps: ['Ouvrez la gestion des photos depuis l’annonce.', 'Ajoutez une image ou utilisez les commandes de modification/suppression sur l’image voulue.', 'Confirmez la modification et retournez à la galerie pour vérifier le résultat.'], result: 'La galerie doit contenir une seule version de chaque photo conservée.' },
    'settings-theme': { location: 'Paramètres → Préférences d’affichage → Apparence', steps: ['Choisissez le thème clair, sombre ou système.', 'Choisissez une couleur d’accent dans la palette.', 'Attendez le message de confirmation avant de quitter la page.'], result: 'Le thème et l’accent s’appliquent immédiatement aux pages compatibles.' },
    'settings-date-format': { location: 'Paramètres → Préférences d’affichage → Format de date', steps: ['Choisissez le format de date souhaité dans la liste.', 'Vérifiez une date affichée sur la page après le changement.'], result: 'Le format d’affichage change, mais les dates enregistrées ne sont pas modifiées.' },
    'settings-compact-tables': { location: 'Paramètres → Affichage → Tableaux compacts', steps: ['Activez Tableaux compacts pour resserrer les lignes.', 'Retournez à une liste pour comparer la densité.', 'Désactivez l’option pour restaurer l’espacement normal.'], result: 'Cette préférence modifie la présentation, pas les résultats ni les droits.' },
    'settings-page-size': { location: 'Paramètres → Affichage → Lignes par page', steps: ['Choisissez 10, 20, 50 ou 100 lignes.', 'Ouvrez une liste compatible et utilisez ses commandes de pagination.', 'Changez la valeur si vous souhaitez afficher plus ou moins de lignes à la fois.'], result: 'La préférence est enregistrée pour votre compte et ne modifie pas le nombre total de résultats.' },
    'settings-shortcut': { location: 'Paramètres → Accès rapide → Afficher l’option d’installation', steps: ['Activez l’option d’installation dans Paramètres.', 'Choisissez Installer ou créer un raccourci lorsqu’il est proposé.', 'Suivez l’invite du navigateur ; sur téléphone, utilisez le menu Partager ou ⋮ selon l’appareil.'], result: 'L’installation dépend du navigateur et d’une connexion sécurisée HTTPS, sauf sur localhost.' },
    'settings-notifications': { location: 'Paramètres → Notifications', steps: ['Choisissez séparément les notifications par e-mail et les messages dans l’application.', 'Activez ou désactivez le contrôle correspondant.', 'Lisez le message de confirmation avant de quitter Paramètres.'], result: 'Le réglage des messages temporaires ne bloque pas l’enregistrement des opérations.' },
    'settings-navigation': { location: 'Paramètres → Affichage → Menu latéral replié au démarrage', steps: ['Activez l’option si vous souhaitez ouvrir l’application avec le menu réduit sur ordinateur.', 'Enregistrez la préférence si la page le demande.', 'Utilisez le bouton du menu latéral pour le déployer pendant la navigation.'], result: 'La préférence ne change pas les pages accessibles ni le comportement des accès.' },
    'settings-account': { location: 'Paramètres → contrôle de la préférence à modifier', steps: ['Ouvrez la section concernée : Profil, Affichage, Accès rapide ou Notifications.', 'Modifiez un réglage à la fois.', 'Attendez la confirmation avant de passer à une autre préférence.'], result: 'Chaque préférence est enregistrée séparément ; en cas d’erreur, la valeur précédente est restaurée.' },
    'org-settings-capacity': { location: 'Paramètres → Organisation → Offre et capacités', steps: ['Ouvrez l’onglet Organisation dans Paramètres.', 'Consultez les compteurs d’usage et plafonds pour chaque ressource.', 'Pour demander un changement, transmettez la ressource et le plafond souhaité au SUPER_ADMIN.'], result: 'Cette section est informative ; les capacités sont modifiées par le SUPER_ADMIN.' },
    'manual-read': { location: 'Paramètres → Manuel d’utilisation', steps: ['Sélectionnez un article dans le sommaire ou recherchez un écran.', 'Développez une étape pour afficher sa procédure, puis consultez les encadrés de résultat et de dépannage.', 'Utilisez Télécharger le PDF si vous avez besoin du guide hors ligne.'], result: 'Le manuel affiché est adapté au rôle et à l’organisation actifs.' },
    'profile-security': { location: 'Page de connexion → Mot de passe oublié', steps: ['Si aucun changement de mot de passe n’est proposé dans le profil, déconnectez-vous.', 'Sur la page de connexion, choisissez Mot de passe oublié et saisissez votre adresse.', 'Ouvrez le lien reçu et suivez les règles affichées pour définir le nouveau mot de passe.'], result: 'Après validation, reconnectez-vous avec le nouveau mot de passe.' },
    'audit-details': { location: 'Administration plateforme → Audit → ligne d’événement', steps: ['Recherchez l’événement avec les filtres disponibles.', 'Sélectionnez sa ligne pour ouvrir le panneau de détail.', 'Consultez le type, la date et les valeurs non sensibles affichées.'], result: 'Les secrets et valeurs sensibles ne sont pas exposés dans le détail.' },
    'publication-images': { location: 'Publication reçue → contenu de l’annonce', steps: ['Ouvrez la publication depuis vos notifications ou votre espace.', 'Faites défiler le contenu jusqu’aux images jointes.', 'Touchez ou sélectionnez une image pour la consulter si un aperçu est proposé.'], result: 'Les images s’adaptent à la largeur de l’écran.' },
    'showcase-settings': { location: 'Menu Vitrine → réglages publics de l’organisation', steps: ['Ouvrez les réglages publics si votre rôle PATRON propose cette section.', 'Modifiez les champs accessibles et vérifiez l’aperçu public.', 'Enregistrez puis contrôlez le résultat dans la vitrine.'], result: 'L’ADMIN_IMMOBILIER peut gérer les annonces autorisées ; les réglages organisationnels restent réservés au PATRON.' },
    'org-create-logo': { location: 'Nouvelle organisation → étape Logo', steps: ['Sélectionnez une image si l’organisation doit avoir un logo dès sa création.', 'Ajustez le recadrage et vérifiez l’aperçu.', 'Poursuivez sans image si le logo n’est pas prêt, puis confirmez la création.'], result: 'Le logo est facultatif et peut être défini plus tard.' },
    'org-create-save': { location: 'Nouvelle organisation → dernière étape → confirmation', steps: ['Vérifiez les informations des étapes Identité, Capacités et Compte PATRON.', 'Contrôlez le logo s’il a été ajouté.', 'Confirmez la création et attendez le message de résultat avant de quitter la page.'], result: 'En cas d’erreur, le formulaire reste disponible avec les valeurs saisies.' },
    'org-edit-limits': { location: 'Modifier une organisation → Capacités et options', steps: ['Comparez l’usage actuel avec le plafond de chaque ressource.', 'Modifiez uniquement les limites nécessaires ; l’application bloque la saisie risquée si l’usage ne peut pas être chargé.', 'Terminez le parcours et confirmez les changements.'], result: 'Une limite inférieure à l’usage ne supprime rien, mais bloque les nouvelles créations concernées.' },
    'org-settings-capacity-over': { location: 'Paramètres → Organisation → Offre et capacités', steps: ['Repérez la ressource dont l’usage dépasse le plafond.', 'Continuez à consulter et modifier les éléments déjà présents selon vos droits.', 'Demandez au SUPER_ADMIN de relever la limite ou réduisez l’usage avant une nouvelle création.'], result: 'Les données existantes ne sont ni supprimées ni cachées.' },
    'profile-edit': { location: 'Menu du compte → Mon profil', steps: ['Ouvrez Modifier dans la section du profil.', 'Mettez à jour les champs disponibles et vérifiez leur contenu.', 'Enregistrez avant de quitter la page.'], result: 'Le message de confirmation s’affiche et les données visibles sont actualisées.' },
    'profile-photo': { location: 'Menu du compte → Mon profil → Photo', steps: ['Choisissez une image puis ajustez le cadrage dans l’outil proposé.', 'Confirmez le recadrage et enregistrez la photo.', 'Vérifiez l’avatar dans le menu latéral sans actualiser la page.'], result: 'La nouvelle photo remplace immédiatement l’ancienne dans l’interface.' },
    'org-capacity-configure': { location: 'Administration plateforme → Organisations → Nouvelle organisation ou Modifier → Capacités', steps: ['Ouvrez la création ou la modification de l’organisation.', 'À l’étape Capacités, choisissez Sans limite ou indiquez un plafond positif pour chaque ressource.', 'Relisez l’utilisation affichée et confirmez la dernière étape.'], result: 'Un plafond limite les prochaines créations ; il ne supprime aucune donnée existante.' },
    'org-capacity-existing-data': { location: 'Administration plateforme → Organisations → Modifier → Capacités', steps: ['Comparez chaque plafond avec le nombre déjà utilisé.', 'Si l’usage dépasse le nouveau plafond, conservez en tête que les données existantes restent en place.', 'Enregistrez seulement après avoir vérifié les limites des nouvelles créations.'], result: 'Les éléments existants restent consultables et modifiables ; les ajouts concernés sont bloqués.' },
    'org-edit-logo': { location: 'Modifier une organisation → étape Logo', steps: ['Choisissez une nouvelle image pour remplacer le logo existant, ou utilisez l’action de retrait si disponible.', 'Contrôlez l’aperçu et le cadrage.', 'Terminez le parcours et confirmez pour enregistrer les changements.'], result: 'Le logo est remplacé ou retiré après la validation finale.' },
    'platform-report-export': { location: 'Administration plateforme → Rapports → filtres puis export', steps: ['Choisissez les filtres de période et de périmètre disponibles.', 'Vérifiez les indicateurs affichés.', 'Utilisez l’action d’export proposée sur cette page.'], result: 'L’export reprend les données correspondant aux filtres sélectionnés.' },
    'publications-images': { location: 'Nouvelle publication → Images', steps: ['Ouvrez la section Images du formulaire.', 'Ajoutez jusqu’à huit images et, si nécessaire, renseignez leur légende.', 'Vérifiez l’aperçu de chaque image avant d’enregistrer.'], result: 'Les images retenues sont jointes à la publication enregistrée.' },
    'publication-read': { location: 'Menu latéral → Publications / notifications → ouvrir l’annonce', steps: ['Ouvrez la notification ou la publication voulue.', 'Parcourez son titre, son contenu et ses images.', 'Utilisez Retour à l’application pour revenir à votre espace.'], result: 'La publication est marquée comme lue lors de son ouverture.' },
    'treasury-movements': { location: 'Menu latéral → Trésorerie → mouvements', steps: ['Choisissez la période et le type de mouvement si les filtres sont proposés.', 'Saisissez une recherche pour réduire la liste.', 'Ouvrez la ligne voulue pour consulter sa provenance.'], result: 'Les résultats sont limités à votre périmètre et aux critères sélectionnés.' },
    'treasury-create': { location: 'Trésorerie → Gestion des dépenses', steps: ['Ouvrez Gestion des dépenses depuis la page Trésorerie.', 'Sélectionnez Enregistrer une dépense et renseignez les champs obligatoires.', 'Vérifiez montant, devise, date et catégorie avant de confirmer.'], result: 'La dépense apparaît dans la liste de gestion et dans les mouvements concernés.' },
    'expenses-date': { location: 'Gestion des dépenses → Enregistrer une dépense → Date', steps: ['Dans le formulaire, repérez le champ de date de dépense.', 'Saisissez le jour réel de la dépense, même si vous enregistrez plus tard.', 'Vérifiez séparément le montant et la devise puis confirmez.'], result: 'La date réelle et la date d’enregistrement restent distinguées dans la traçabilité.' },
    'tenants-create': { location: 'Menu latéral → Locataires → Ajouter un locataire (ADMIN_VILLE)', steps: ['Vérifiez que la ville active est celle où le locataire doit être géré.', 'Sélectionnez Ajouter un locataire et complétez les informations demandées.', 'Vérifiez les coordonnées et enregistrez avant de créer un bail séparément.'], result: 'Le PATRON et l’ADMIN_IMMOBILIER consultent les fiches ; la création locale appartient à l’ADMIN_VILLE affecté.' },
    'tenants-correction': { location: 'Locataires → fiche → Historique résidentiel → mouvement concerné', steps: ['Ouvrez le mouvement et vérifiez qu’il n’a pas déjà été réalisé.', 'Choisissez Corriger ou Annuler, indiquez le motif demandé.', 'Confirmez uniquement après avoir vérifié la date et la personne concernée.'], result: 'Les corrections sont tracées ; un mouvement réalisé reste verrouillé.' },
    'tenants-archive': { location: 'Locataires → fiche du locataire → menu Actions', steps: ['Vérifiez qu’aucun bail ou mouvement ne bloque l’archivage.', 'Choisissez Archiver dans les actions de la fiche si cette commande est disponible.', 'Lisez la confirmation et validez.'], result: 'L’archivage respecte les règles métier et ne supprime pas l’historique.' },
    'rents-payment-date': { location: 'Loyers → échéance → Enregistrer un paiement → Date du paiement', steps: ['Ouvrez l’échéance concernée et le formulaire de paiement.', 'Saisissez la date à laquelle le locataire a réellement payé.', 'Vérifiez les autres champs puis validez le paiement.'], result: 'La date réelle du paiement est conservée avec la date d’enregistrement et son auteur.' },
    'maintenance-reported-date': { location: 'Maintenance → Nouvelle intervention → date du signalement', steps: ['Choisissez le bien concerné.', 'Indiquez la date à laquelle le problème a réellement été constaté.', 'Complétez les détails de l’intervention et enregistrez.'], result: 'La date du signalement peut précéder la date de saisie.' },
    'maintenance-completed-date': { location: 'Maintenance → intervention en cours → Terminer', steps: ['Ouvrez les actions de l’intervention en cours.', 'Choisissez Terminer et saisissez la date réelle d’achèvement.', 'Vérifiez que la date est comprise entre le signalement et aujourd’hui puis confirmez.'], result: 'Le statut passe à terminé et la transition reste tracée.' },
    'maintenance-scope': { location: 'Maintenance → sélecteur de ville en haut de page', steps: ['Ouvrez le sélecteur de ville.', 'Choisissez une ville attribuée à votre compte.', 'Consultez la liste des interventions pour cette ville.'], result: 'Les résultats sont limités à la ville et au périmètre autorisés.' },
    'operations-scope': { location: 'Menu latéral → Opérations → sélecteur ou filtre de ville', steps: ['Choisissez une ville attribuée à votre compte.', 'Consultez les affectations de cette ville.', 'Changez de ville uniquement parmi celles proposées par le sélecteur.'], result: 'Les affectations des autres villes ne sont pas exposées dans cette liste.' },
    'property-structure-change': { location: 'Patrimoine → Unités → modifier la hiérarchie d’un espace', steps: ['Vérifiez les baux actifs de l’espace, de son parent et de ses sous-espaces.', 'Clôturez ou transférez les baux qui empêchent la modification.', 'Revenez à la fiche de l’espace et modifiez son rattachement si l’action est alors permise.'], result: 'L’application bloque les changements de structure incompatibles avec un bail actif.' },
    'property-house-entire': { location: 'Patrimoine → Unités → Ajouter une unité', steps: ['Sélectionnez le bâtiment concerné.', 'Créez une unité représentant la maison entière, sans créer une unité par pièce.', 'Enregistrez puis choisissez cette unité lors de la création du bail.'], result: 'Le bail sur l’unité parent empêche la location simultanée de ses subdivisions.' },
    'property-floor-split': { location: 'Patrimoine → Unités → Ajouter une unité', steps: ['Sélectionnez le bâtiment concerné.', 'Créez une unité distincte pour chaque logement à louer séparément.', 'Attribuez à chacune le même niveau et une référence différente.'], result: 'Les unités sœurs peuvent être louées séparément.' },
    'property-usage-change': { location: 'Patrimoine → Unités → menu Actions → Modifier', steps: ['Ouvrez l’unité et vérifiez qu’aucun bail actif ne dépend de cet espace.', 'Choisissez le nouveau type et corrigez les informations nécessaires.', 'Enregistrez puis contrôlez le libellé et les conditions locatives.'], result: 'Les contrats et paiements historiques restent attachés à cette unité.' },
    'property-composite-lease': { location: 'Locataires → créer un bail → unités supplémentaires', steps: ['Choisissez l’unité principale du bail.', 'Ajoutez les autres espaces concernés dans la même ville.', 'Saisissez le montant global du loyer et vérifiez tous les espaces avant validation.'], result: 'Un bail actif incompatible sur un espace ou ses subdivisions bloque la création.' },
    'property-household': { location: 'Locataires → créer un bail → personnes associées', steps: ['Créez une fiche distincte pour chaque personne qui doit être enregistrée.', 'Dans le bail, ajoutez les personnes et choisissez cotitulaire ou occupant.', 'Renseignez les dates propres à chacune puis vérifiez le titulaire principal.'], result: 'Le titulaire principal reste responsable des conditions financières du bail.' },
    'property-mixed-use': { location: 'Patrimoine → Unités → Ajouter ou Modifier une unité', steps: ['Ouvrez le bâtiment concerné.', 'Créez une unité ou modifiez son type selon l’usage réel.', 'Répétez pour chaque espace commercial ou résidentiel.'], result: 'Un bâtiment peut contenir des unités de types différents.' },
    'property-restructure-history': { location: 'Patrimoine → Unités → vérifier les baux avant toute restructuration', steps: ['Repérez les baux actifs de l’espace parent et de ses subdivisions.', 'Terminez ou transférez les baux concernés avant de changer la hiérarchie.', 'Effectuez ensuite la modification et consignez tout changement d’occupation dans l’historique résidentiel.'], result: 'Les anciens baux et paiements restent conservés et liés à leurs espaces d’origine.' },
    'staff-find': { location: 'Menu latéral → Personnel → recherche et filtre de ville', steps: ['Saisissez le nom ou l’adresse du membre dans la recherche.', 'Choisissez une ville si le filtre est disponible.', 'Ouvrez sa fiche pour consulter ses affectations.'], result: 'Le compteur et la liste des affectations indiquent les missions enregistrées.' },
    'admin-member-capacity': { location: 'Paramètres → Organisation → Offre et capacités', steps: ['Consultez le nombre de membres actifs et le plafond affiché.', 'Si la limite est atteinte, identifiez les nouveaux comptes bloqués.', 'Demandez au SUPER_ADMIN d’ajuster la capacité de l’organisation.'], result: 'Aucun compte déjà créé n’est supprimé par un plafond atteint.' },
    'showcase-entitlement': { location: 'La permission est configurée par le SUPER_ADMIN dans Administration plateforme → Organisations → Modifier → Capacités et options', steps: ['Demandez au SUPER_ADMIN d’ouvrir la fiche de votre organisation.', 'Il active l’option Vitrine et confirme la modification.', 'Reconnectez-vous à l’espace de l’organisation ou rechargez le menu si nécessaire.'], result: 'Le menu Vitrine apparaît pour PATRON et ADMIN_IMMOBILIER ; il n’est pas disponible pour ADMIN_VILLE.' },
    'org-settings-currency': { location: 'Paramètres → Organisation → section Devise', steps: ['Choisissez USD ou CDF comme devise de référence.', 'Enregistrez le réglage.', 'Vérifiez la devise affichée dans les synthèses concernées.'], result: 'Les montants de devises différentes restent distincts dans les listes et rapports.' },
    'org-settings-rate': { location: 'Paramètres → Organisation → taux de change', steps: ['Choisissez le périmètre organisation ou ville lorsqu’il est proposé.', 'Saisissez le taux et sa date d’effet.', 'Enregistrez puis vérifiez la ligne correspondante dans l’historique.'], result: 'L’historique indique la date d’effet et les éventuelles dérogations locales.' },
    'manual-sections': { location: 'Paramètres → Manuel d’utilisation', steps: ['Utilisez le champ de recherche pour saisir un nom d’écran ou d’action.', 'Ouvrez la rubrique correspondante dans le sommaire, puis développez ses étapes.', 'Lisez l’objectif, la procédure, les éléments à préparer et le résultat attendu.'], result: 'Le contenu du manuel suit le rôle et l’organisation actifs.' },
};

function addActionGuides(questions: readonly HelpQuestion[]): HelpQuestion[] {
    return questions.map((question) => {
        const guide = actionGuides[question.id];
        return guide ? { ...question, guide } : question;
    });
}

const roleMenus = [
    { platformRole: PLATFORM_ROLE, organizationRole: null },
    ...ORG_ROLES.map((organizationRole) => ({ platformRole: null, organizationRole })),
] as const;

/** Construit l’inventaire à partir des vraies routes et menus afin de détecter les nouvelles pages. */
export function buildContextualHelpInventory(): PageHelp[] {
    const inventory: PageHelp[] = [];
    for (const route of buildAppRoutes()) {
        const roles = roleMenus.flatMap(({ platformRole, organizationRole }) =>
            resolveSidebar(platformRole, organizationRole, true).some((item) => item.path === route.path || (item.path && route.path.startsWith(`${item.path}/`)))
                ? [platformRole ?? organizationRole]
                : []
        ).filter((role): role is Exclude<HelpRole, null> => role !== null);
        const definition = content[route.path];
        if (definition) inventory.push({ path: route.path, roles, ...definition });
    }
    // Pages de compte hors menu, montées dans le même layout authentifié.
    for (const path of ['/profile', '/settings', '/settings/organisation']) {
        const definition = content[path];
        if (definition) inventory.push({ path, roles: [...ORG_ROLES, PLATFORM_ROLE], ...definition });
    }
    for (const path of ['/app/publications', '/settings/manuel']) {
        const definition = content[path];
        if (definition) inventory.push({ path, roles: path === '/app/publications' ? [...ORG_ROLES] : [...ORG_ROLES, PLATFORM_ROLE], ...definition });
    }
    const expenseManagement = content['/app/depenses/gestion'];
    if (expenseManagement) inventory.push({ path: '/app/depenses/gestion', roles: [...ORG_ROLES], ...expenseManagement });
    for (const path of ['/login', '/forgot-password', '/reset-password']) {
        const definition = content[path];
        if (definition) inventory.push({ path, roles: [null], ...definition });
    }
    const denied = content['/app/access-denied'];
    if (denied) inventory.push({ path: '/app/access-denied', roles: [null, ...ORG_ROLES, PLATFORM_ROLE], ...denied });
    const organizationProfile = content['/app/organisation'];
    if (organizationProfile) inventory.push({ path: '/app/organisation', roles: ['patron', 'admin_immobilier'], ...organizationProfile });
    for (const path of ['/app/admin/organisations/nouvelle', '/app/admin/organisations/:uuid/modifier']) {
        const definition = content[path];
        if (definition) inventory.push({ path, roles: [PLATFORM_ROLE], ...definition });
    }
    return inventory;
}

const inventory = buildContextualHelpInventory();

type PatrimoineHelpSection = 'overview' | 'villes' | 'parcelles' | 'batiments' | 'unites';

const patrimoineQuestionsByRoleAndSection: Record<OrganizationRole, Record<PatrimoineHelpSection, readonly string[]>> = {
    patron: {
        overview: ['property-navigate', 'property-patron-scope', 'property-relations', 'property-capacity-limit'],
        villes: ['property-patron-scope', 'property-search', 'property-relations'],
        parcelles: ['property-patron-scope', 'property-search', 'property-map', 'property-relations'],
        batiments: ['property-patron-scope', 'property-nested-spaces', 'property-mixed-use', 'property-access', 'property-building-access'],
        unites: ['property-patron-scope', 'property-nested-spaces', 'property-house-entire', 'property-floor-split', 'property-mixed-use', 'property-partial-occupancy', 'property-usage-change', 'property-composite-lease', 'property-household'],
    },
    admin_immobilier: {
        overview: ['property-navigate', 'property-adminimmo-actions', 'property-city-admin-assignment', 'property-relations', 'property-capacity-limit'],
        villes: ['property-adminimmo-actions', 'property-city-admin-assignment', 'property-search', 'property-capacity-limit'],
        parcelles: ['property-adminimmo-actions', 'property-create', 'property-search', 'property-map', 'property-relations', 'property-capacity-limit'],
        batiments: ['property-adminimmo-actions', 'property-create', 'property-nested-spaces', 'property-mixed-use', 'property-access', 'property-building-access', 'property-relations'],
        unites: ['property-adminimmo-actions', 'property-create', 'property-nested-spaces', 'property-house-entire', 'property-floor-split', 'property-mixed-use', 'property-access', 'property-usage-change', 'property-partial-occupancy'],
    },
    admin_ville: {
        overview: ['property-adminville-scope', 'property-navigate', 'property-relations', 'property-search', 'property-capacity-limit'],
        villes: ['property-adminville-scope', 'property-relations'],
        parcelles: ['property-adminville-scope', 'property-create', 'property-search', 'property-map', 'property-relations', 'property-capacity-limit'],
        batiments: ['property-adminville-scope', 'property-create', 'property-nested-spaces', 'property-mixed-use', 'property-access', 'property-building-access', 'property-structure-change', 'property-capacity-limit'],
        unites: ['property-adminville-scope', 'property-create', 'property-nested-spaces', 'property-house-entire', 'property-floor-split', 'property-mixed-use', 'property-access', 'property-composite-lease', 'property-partial-occupancy', 'property-usage-change', 'property-restructure-history'],
    },
};

function resolvePatrimoineHelpSection(pathname: string, organizationRole: OrganizationRole): PatrimoineHelpSection {
    const segments = pathname.split('/').filter(Boolean);
    if (segments.includes('unites')) return 'unites';
    if (segments.includes('batiments')) return 'batiments';
    if (segments.includes('parcelles')) return 'parcelles';
    if (segments.includes('villes')) return 'villes';
    return organizationRole === 'admin_ville' ? 'parcelles' : 'overview';
}

export function resolveContextualHelp(pathname: string, platformRole: PlatformRole | null, organizationRole: OrganizationRole | null): PageHelp | null {
    const role: HelpRole = platformRole ?? organizationRole;
    const candidate = inventory
        .filter((page) => pathname === page.path
            || pathname.startsWith(`${page.path}/`)
            || (page.path === '/app/admin/organisations/:uuid/modifier' && /^\/app\/admin\/organisations\/[0-9a-f-]{36}\/modifier$/i.test(pathname)))
        .sort((a, b) => b.path.length - a.path.length)[0];
    if (!candidate || !candidate.roles.includes(role)) return null;
    let questions = candidate.questions.filter((question) => !question.roles || (role !== null && question.roles.includes(role)));
    if (candidate.path === '/app/patrimoine' && organizationRole !== null) {
        const section = resolvePatrimoineHelpSection(pathname, organizationRole);
        const relevantIds = new Set(patrimoineQuestionsByRoleAndSection[organizationRole][section]);
        questions = questions.filter((question) => relevantIds.has(question.id));
        return {
            ...candidate,
            title: `Patrimoine · ${{ overview: 'Vue d’ensemble', villes: 'Villes', parcelles: 'Parcelles', batiments: 'Bâtiments', unites: 'Unités' }[section]}`,
            description: 'Aide adaptée à cet écran et à votre rôle actif.',
            questions: addActionGuides(questions),
        };
    }
    return { ...candidate, questions: addActionGuides(questions) };
}

export function filterHelpQuestions(questions: readonly HelpQuestion[], search: string): HelpQuestion[] {
    const normalize = (value: string) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('fr')
        .replace(/\s+/g, ' ')
        .trim();
    const query = normalize(search);
    if (!query) return [...questions];
    return questions.filter(({ question, answer, keywords = [], guide }) =>
        [question, answer, ...keywords, guide?.location ?? '', ...(guide?.steps ?? []), guide?.result ?? ''].some((value) => normalize(value).includes(query))
    );
}
