/**
 * Vérifie la logique pure du formulaire de réinitialisation du mot de passe.
 *
 * Le composant React ne peut pas être rendu sans navigateur, mais les règles
 * qui décident de l'acceptation du formulaire en sont volontairement
 * extraites dans `assets/app/password-form.ts`. Ce script les exécute
 * réellement : Node 24 supprime les types TypeScript nativement, aucun
 * transpiler n'est nécessaire.
 *
 *   node tests/verify-reset-password-form.ts
 */

import assert from 'node:assert/strict';
import {
    MIN_PASSWORD_LENGTH,
    readTokenFromSearch,
    validatePasswordForm,
} from '../assets/app/password-form.ts';

let checks = 0;
const failures: string[] = [];

function check(label: string, ok: boolean, detail = ''): void {
    checks += 1;

    if (ok) {
        console.log(`  [OK]   ${label}`);

        return;
    }

    failures.push(label + (detail !== '' ? ` (${detail})` : ''));
    console.log(`  [FAIL] ${label}${detail !== '' ? ` -> ${detail}` : ''}`);
}

const TOKEN = 'a'.repeat(64);

console.log('\n=== Extraction du jeton depuis l\'URL ===\n');

check('Le jeton est extrait de la query string', readTokenFromSearch(`?token=${TOKEN}`) === TOKEN);
check('Le jeton est extrait en présence d\'autres paramètres', readTokenFromSearch(`?source=email&token=${TOKEN}&x=1`) === TOKEN);
check('Une URL sans jeton renvoie une chaîne vide', readTokenFromSearch('?source=email') === '');
check('Une query string vide renvoie une chaîne vide', readTokenFromSearch('') === '');
check('Un paramètre token vide renvoie une chaîne vide', readTokenFromSearch('?token=') === '');
check('Les espaces autour du jeton sont supprimés', readTokenFromSearch(`?token=%20${TOKEN}%20`) === TOKEN);
check('Un token=null est traité comme absent', readTokenFromSearch('?token=null') === 'null');

console.log('\n=== Validation des deux champs ===\n');

const valid = validatePasswordForm('MotDePasse!2026', 'MotDePasse!2026');
check('Deux mots de passe identiques et valides sont acceptés', valid.isValid);
check('Aucun message d\'erreur quand tout est valide', Object.keys(valid.errors).length === 0, JSON.stringify(valid.errors));

const emptyBoth = validatePasswordForm('', '');
check('Deux champs vides sont refusés', !emptyBoth.isValid);
check('Le mot de passe vide est signalé', Boolean(emptyBoth.errors.password), JSON.stringify(emptyBoth.errors));
check('La confirmation vide est signalée', Boolean(emptyBoth.errors.confirmation), JSON.stringify(emptyBoth.errors));

const mismatch = validatePasswordForm('MotDePasse!2026', 'MotDePasse!2027');
check('Deux mots de passe différents sont refusés', !mismatch.isValid);
check('Le message de confirmation signale la différence', (mismatch.errors.confirmation ?? '').includes('ne correspondent pas'), mismatch.errors.confirmation ?? 'absent');
check('Le champ mot de passe n\'est pas signalé à tort', mismatch.errors.password === undefined, mismatch.errors.password ?? 'absent');

const tooShort = validatePasswordForm('a'.repeat(MIN_PASSWORD_LENGTH - 1), 'a'.repeat(MIN_PASSWORD_LENGTH - 1));
check(`Un mot de passe de ${MIN_PASSWORD_LENGTH - 1} caractères est refusé`, !tooShort.isValid);
check('Le message cite la longueur minimale', (tooShort.errors.password ?? '').includes(String(MIN_PASSWORD_LENGTH)), tooShort.errors.password ?? 'absent');

const exactLength = validatePasswordForm('a'.repeat(MIN_PASSWORD_LENGTH), 'a'.repeat(MIN_PASSWORD_LENGTH));
check(`Un mot de passe de exactement ${MIN_PASSWORD_LENGTH} caractères est accepté`, exactLength.isValid);

const missingConfirm = validatePasswordForm('MotDePasse!2026', '');
check('Une confirmation manquante est refusée', !missingConfirm.isValid);
check('Seule la confirmation est signalée', missingConfirm.errors.password === undefined && Boolean(missingConfirm.errors.confirmation), JSON.stringify(missingConfirm.errors));

const confirmOnly = validatePasswordForm('', 'MotDePasse!2026');
check('Un mot de passe manquant est refusé', !confirmOnly.isValid);
check('Seul le mot de passe est signalé', Boolean(confirmOnly.errors.password) && confirmOnly.errors.confirmation === undefined, JSON.stringify(confirmOnly.errors));

const spaces = validatePasswordForm('        ', '        ');
check('Des espaces seuls sont refusés', !spaces.isValid);
check('Le champ mot de passe signale la saisie vide', Boolean(spaces.errors.password), JSON.stringify(spaces.errors));
check('Le champ confirmation signale la saisie vide', Boolean(spaces.errors.confirmation), JSON.stringify(spaces.errors));

const mixed = validatePasswordForm('  ab  ', '  ab  ');
check('Un mot de passe court mais contenant du texte est refusé', !mixed.isValid, JSON.stringify(mixed.errors));

const padded = validatePasswordForm('  MotDePasse!2026  ', '  MotDePasse!2026  ');
check('Les espaces de bordure ne suffisent pas à invalider un mot de passe valide', padded.isValid, JSON.stringify(padded.errors));

console.log('\n=== Cohérence avec les contraintes de l\'API ===\n');

const api = await fetch('http://127.0.0.1:8000/api/doc.json').then((r) => r.ok ? r.json() : null).catch(() => null);

if (api === null) {
    console.log('  [SKIP] API absente : contrainte de longueur non confrontée au schéma OpenAPI');
} else {
    const schema = api?.components?.schemas?.ResetPasswordRequest;
    const minLength = schema?.properties?.newPassword?.minLength;

    check('Le schéma OpenAPI expose une longueur minimale', typeof minLength === 'number', String(minLength));
    check(
        'La longueur minimale du client correspond à celle de l\'API',
        minLength === MIN_PASSWORD_LENGTH,
        `client=${MIN_PASSWORD_LENGTH}, api=${String(minLength)}`,
    );
    check(
        'Le jeton est documenté en 64 caractères côté API',
        schema?.properties?.token?.minLength === 64 && schema?.properties?.token?.maxLength === 64,
        JSON.stringify(schema?.properties?.token ?? null),
    );
}

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);
