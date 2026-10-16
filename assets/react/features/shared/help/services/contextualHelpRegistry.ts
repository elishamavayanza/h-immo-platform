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
            { id: 'org-suspend', question: 'Comment suspendre ou réactiver une organisation ?', answer: 'Ouvrez l’action de suspension de l’organisation, choisissez la durée proposée et indiquez le motif demandé. Pour réactiver avant la fin, utilisez l’action de réactivation de sa fiche. La suspension bloque l’accès selon les règles du compte.' },
            { id: 'org-errors', question: 'Pourquoi une organisation est-elle absente ou inaccessible ?', answer: 'Vérifiez les filtres et l’état du compte. Une organisation n’est visible que dans le périmètre d’administration autorisé ; les restrictions d’accès doivent être traitées par l’administration plateforme.' },
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
        description: 'Parcourez la structure immobilière : villes, parcelles, bâtiments et unités.',
        questions: [
            { id: 'property-navigate', question: 'Comment parcourir le patrimoine ?', answer: 'Commencez par la ville, puis ouvrez une parcelle, un bâtiment et une unité pour descendre dans la hiérarchie. Utilisez le fil d’Ariane de la page pour revenir au niveau précédent.' },
            { id: 'property-create', question: 'Comment ajouter un élément immobilier ?', answer: 'Choisissez d’abord le niveau concerné et la ville ou le parent demandé. Utilisez le bouton d’ajout présent à ce niveau, complétez les champs obligatoires affichés et enregistrez.' },
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
            { id: 'admin-readonly', question: 'Pourquoi certaines commandes ne sont-elles pas disponibles ?', answer: 'Cette page ne montre que les opérations permises par votre rôle et la délégation active. Une commande absente nécessite une autorisation du responsable concerné.' },
        ],
    },
    '/app/organisation': {
        title: 'Organisation', description: 'Consultez et mettez à jour les informations générales et le logo de l’organisation.',
        questions: [
            { id: 'organization-profile', question: 'Comment modifier les informations de l’organisation ?', answer: 'Dans la section Identité légale, modifiez les champs autorisés puis sélectionnez « Enregistrer ». Les informations non modifiables sont affichées en lecture seule.' },
            { id: 'organization-logo', question: 'Comment mettre à jour le logo ?', answer: 'Choisissez un fichier avec le contrôle de logo, vérifiez son aperçu puis enregistrez le logo. Si l’enregistrement échoue, vérifiez le format et la taille acceptés.' },
            { id: 'organization-access', question: 'Pourquoi ne puis-je pas modifier ces informations ?', answer: 'Les opérations disponibles dépendent de votre rôle et des champs modifiables dans cette page. Seul un rôle autorisé peut enregistrer les changements.' },
        ],
    },
    '/app/vitrine': {
        title: 'Vitrine',
        description: 'Gérez les annonces immobilières et les réglages publics disponibles pour votre rôle.',
        questions: [
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
            { id: 'settings-account', question: 'Comment modifier mes préférences ?', answer: 'Modifiez les contrôles disponibles dans la section concernée puis utilisez le bouton d’enregistrement correspondant. Un retour de confirmation indique si la sauvegarde a abouti.' },
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
        ],
    },
    '/profile': {
        title: 'Mon profil',
        description: 'Consultez et mettez à jour les informations de votre compte.',
        questions: [
            { id: 'profile-edit', question: 'Comment modifier mon profil ?', answer: 'Cliquez sur « Modifier », mettez à jour les champs proposés et enregistrez. Vérifiez le message de confirmation avant de quitter la page.' },
            { id: 'profile-photo', question: 'Comment changer ma photo ?', answer: 'Utilisez le contrôle de photo du profil, sélectionnez un fichier accepté et enregistrez la modification. En cas d’échec, vérifiez le format et la taille indiqués par l’interface.' },
            { id: 'profile-security', question: 'Où modifier mon mot de passe ?', answer: 'Utilisez le parcours « Mot de passe oublié » depuis la page de connexion si aucun formulaire de changement n’est proposé dans le profil.' },
        ],
    },
};

const roleMenus = [
    { platformRole: PLATFORM_ROLE, organizationRole: null },
    ...ORG_ROLES.map((organizationRole) => ({ platformRole: null, organizationRole })),
] as const;

/** Construit l’inventaire à partir des vraies routes et menus afin de détecter les nouvelles pages. */
export function buildContextualHelpInventory(): PageHelp[] {
    const inventory: PageHelp[] = [];
    for (const route of buildAppRoutes()) {
        const roles = roleMenus.flatMap(({ platformRole, organizationRole }) =>
            resolveSidebar(platformRole, organizationRole).some((item) => item.path === route.path || (item.path && route.path.startsWith(`${item.path}/`)))
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
    const expenseManagement = content['/app/depenses/gestion'];
    if (expenseManagement) inventory.push({ path: '/app/depenses/gestion', roles: [...ORG_ROLES], ...expenseManagement });
    for (const path of ['/login', '/forgot-password', '/reset-password']) {
        const definition = content[path];
        if (definition) inventory.push({ path, roles: [null], ...definition });
    }
    const denied = content['/app/access-denied'];
    if (denied) inventory.push({ path: '/app/access-denied', roles: [null, ...ORG_ROLES, PLATFORM_ROLE], ...denied });
    const organizationProfile = content['/app/organisation'];
    if (organizationProfile) inventory.push({ path: '/app/organisation', roles: ['patron'], ...organizationProfile });
    return inventory;
}

const inventory = buildContextualHelpInventory();

export function resolveContextualHelp(pathname: string, platformRole: PlatformRole | null, organizationRole: OrganizationRole | null): PageHelp | null {
    const role: HelpRole = platformRole ?? organizationRole;
    const candidate = inventory
        .filter((page) => pathname === page.path || pathname.startsWith(`${page.path}/`))
        .sort((a, b) => b.path.length - a.path.length)[0];
    if (!candidate || !candidate.roles.includes(role)) return null;
    const questions = candidate.questions.filter((question) => !question.roles || (role !== null && question.roles.includes(role)));
    return { ...candidate, questions };
}

export function filterHelpQuestions(questions: readonly HelpQuestion[], search: string): HelpQuestion[] {
    const query = search.trim().toLocaleLowerCase('fr');
    if (!query) return [...questions];
    return questions.filter(({ question, answer, keywords = [] }) =>
        [question, answer, ...keywords].some((value) => value.toLocaleLowerCase('fr').includes(query))
    );
}
