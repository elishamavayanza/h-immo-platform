<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Identity\User;
use App\Enum\PlatformRole;
use App\Repository\Identity\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * CreateSuperAdminCommand
 *
 * Package : Identity & Access — Commande de provisioning
 *
 * Point d'entrée d'amorçage de la plateforme. Le tout premier SUPER_ADMIN
 * ne peut pas être créé via l'API : `UserService::create()` et
 * `OrganizationService::create()` exigent tous deux un SUPER_ADMIN déjà
 * authentifié. Sans cette commande, la plateforme est inutilisable.
 *
 * Le mot de passe N'EST JAMAIS stocké en base en clair et n'est JAMAIS
 * écrit dans un fichier versionné : il est soit fourni par l'opérateur
 * (`--password` ou variable d'environnement `DEFAULT_ADMIN_PASSWORD`
 * définie dans `.env.local`, non versionné), soit généré aléatoirement et
 * affiché une seule fois sur la sortie standard.
 */
#[AsCommand(
    name: 'app:super-admin:create',
    description: 'Crée ou réinitialise le compte administrateur de la plateforme (SUPER_ADMIN).'
)]
final class CreateSuperAdminCommand extends Command
{
    /**
     * Longueur minimale du mot de passe : un compte de plateforme donne
     * accès à l'intégrité multi-tenant, la convention de Symfony impose
     * au minimum 12 caractères.
     */
    private const MIN_PASSWORD_LENGTH = 12;

    /**
     * Longueur du mot de passe aléatoire généré en l'absence de
     * `--password` et de `DEFAULT_ADMIN_PASSWORD` (20 caractères, soit
     * ~119 bits d'entropie sur un alphabet de 62 symboles).
     */
    private const GENERATED_PASSWORD_LENGTH = 20;

    /**
     * Alphabet du générateur : ASCII imprimable, sans quote ni antislash
     * afin que le mot de passe reste saisissable sans échappement dans un
     * shell ou un en-tête HTTP.
     */
    private const PASSWORD_ALPHABET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%^*_-+';

    /**
     * Injecte le repository utilisateurs, l'EntityManager et le service de
     * hachage. Les valeurs par défaut des options sont lues dans
     * l'environnement au moment de l'exécution (voir `env()`).
     */
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    /**
     * Déclare les options de la commande. Toutes sont optionnelles : les
     * valeurs par défaut proviennent des variables d'environnement afin que
     * l'amorçage soit scriptable sans rendre le mot de passe visible dans
     * l'historique du shell.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email du super administrateur. Défaut : variable DEFAULT_ADMIN_EMAIL.'
            )
            ->addOption(
                'full-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Nom complet du super administrateur. Défaut : variable DEFAULT_ADMIN_FULL_NAME.'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe en clair. Défaut : variable DEFAULT_ADMIN_PASSWORD, sinon génération aléatoire.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Autorise la promotion d\'un compte existant qui n\'est pas encore SUPER_ADMIN.'
            )
            ->setHelp(<<<'HELP'
                La commande <info>%command.name%</info> crée le compte SUPER_ADMIN de la plateforme,
                indispensable avant toute autre opération (création d'Organization, etc.).

                Ordre de résolution du mot de passe :
                  1. option <info>--password</info>
                  2. variable d'environnement <info>DEFAULT_ADMIN_PASSWORD</info>
                     (à définir dans <comment>.env.local</comment>, non versionné)
                  3. génération aléatoire affichée une seule fois

                Exemples :
                  <info>php %command.full_name%</info>
                  <info>php %command.full_name% --email=admin@himmo.cd --password='UnMotDePasseSolide!'</info>
                  <info>php %command.full_name% --force</info>  (réinitialise le mot de passe existant)
                HELP);
    }

    /**
     * Exécute l'amorçage : crée le compte, ou réinitialise son mot de passe
     * s'il existe déjà. La commande est idempotente.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) ($input->getOption('email') ?: $this->env('DEFAULT_ADMIN_EMAIL')));
        $fullName = trim((string) ($input->getOption('full-name') ?: $this->env('DEFAULT_ADMIN_FULL_NAME')));
        $force = (bool) $input->getOption('force');

        if ($email === '') {
            $io->error('Aucun email fourni : utilisez --email ou définissez DEFAULT_ADMIN_EMAIL dans .env.');

            return Command::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('L\'email « %s » n\'est pas une adresse valide.', $email));

            return Command::FAILURE;
        }

        $fullName = $fullName !== '' ? $fullName : 'Administrateur H-Immo';

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNew = $user === null;

        if ($user !== null && $user->getPlatformRole() !== PlatformRole::SUPER_ADMIN && !$force) {
            // Refus explicite : sans --force, la commande ne doit jamais
            // transformer un compte métier existant (PATRON, ADMIN_VILLE...)
            // en compte de plateforme. Cela contournerait le flux d'onboarding
            // et l'isolation multi-tenant.
            $io->error(sprintf(
                'Le compte « %s » existe déjà et porte le rôle %s. Refus de le promouvoir en SUPER_ADMIN : utilisez --force si c\'est intentionnel.',
                $email,
                $user->getPlatformRole()?->value ?? 'AUCUN'
            ));

            return Command::FAILURE;
        }

        $plainPassword = (string) ($input->getOption('password') ?: $this->env('DEFAULT_ADMIN_PASSWORD'));
        $generated = false;

        if ($plainPassword === '') {
            $plainPassword = $this->generatePassword();
            $generated = true;
        }

        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf(
                'Le mot de passe doit contenir au moins %d caractères (reçu : %d).',
                self::MIN_PASSWORD_LENGTH,
                mb_strlen($plainPassword)
            ));

            return Command::FAILURE;
        }

        $user ??= new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setPlatformRole(PlatformRole::SUPER_ADMIN);
        $user->setIsActive(true);

        // Un compte supprimé logiquement porterait toujours la contrainte
        // d'unicité sur l'email : on le restaure au lieu d'échouer.
        $user->restore();

        // Le hachage doit intervenir APRÈS avoir renseigné l'email et le
        // rôle : le choix de l'algorithme ("auto") s'appuie sur l'empreinte
        // courante du compte pour migrer un ancien format si besoin.
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        $io->success($isNew
            ? 'Compte SUPER_ADMIN créé.'
            : 'Compte SUPER_ADMIN réinitialisé.');

        $io->definitionList(
            ['Email' => $email],
            ['Nom complet' => $fullName],
            ['Rôle plateforme' => PlatformRole::SUPER_ADMIN->value],
            ['Mot de passe' => $generated ? $plainPassword . ' (généré, à changer)' : '(celui fourni)']
        );

        if ($generated) {
            $io->note('Ce mot de passe n\'est affiché qu\'une fois et n\'est stocké qu\'haché. Conservez-le en lieu sûr.');
        }

        $io->comment('Première connexion : POST /api/auth/login');

        return Command::SUCCESS;
    }

    /**
     * Lit une variable d'environnement, en tolérant son absence (les
     * variables `DEFAULT_ADMIN_*` sont facultatives).
     *
     * Les variables issues de `.env` ne sont PAS des paramètres du
     * conteneur : elles ne sont résolues que via `%env()%`. La lecture
     * directe de la superglobale est donc la seule voie fiable ici, et
     * reste correctement priorisée (`$_SERVER` avant `$_ENV`, puis
     * l'environnement réel du processus pour les variables injectées par
     * Docker/systemd).
     */
    private function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Génère un mot de passe aléatoire cryptographiquement sûr.
     * `random_int` est CSPRNG : il ne s'agit pas de `rand`/`mt_rand`.
     */
    private function generatePassword(): string
    {
        $alphabetLength = strlen(self::PASSWORD_ALPHABET);
        $password = '';

        for ($i = 0; $i < self::GENERATED_PASSWORD_LENGTH; ++$i) {
            $password .= self::PASSWORD_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $password;
    }
}
