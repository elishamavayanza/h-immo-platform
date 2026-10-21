import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');

const [rentService, rentTable, photoService, photoGallery, unitController, showcaseService] = await Promise.all([
    read('../assets/react/features/admin_immobilier/loyers/services/loyersService.ts'),
    read('../assets/react/features/admin_immobilier/loyers/components/LoyersTable.tsx'),
    read('../assets/react/features/shared/media/services/mediaService.ts'),
    read('../assets/react/features/shared/media/components/PhotoGallery.tsx'),
    read('../src/Controller/Api/Property/UnitController.php'),
    read('../src/Service/Property/PublicShowcaseManagementService.php'),
]);

assert.doesNotMatch(rentService, /\/v1\/rents\/\$\{[^}]+\}\/overdue/,
    'The frontend must not call a route that does not exist to persist overdue status.');
assert.doesNotMatch(rentTable, /Marquer en retard/,
    'Overdue must remain a status computed from rent due date and payments.');
assert.match(rentTable, /état calculé par le serveur/,
    'The rent table must explain that overdue is derived.');

assert.match(unitController, /Route\('\/\{uuid\}\/photos\/deleted', name: 'list_deleted_photos', methods: \['GET'\]\)/,
    'The API must expose a unit-scoped list of recoverable photos.');
assert.match(showcaseService, /function listDeletedPhotos\(Unit \$unit\)[\s\S]*?checkUnitPublishAccess\(\$unit, SecurityAction::PUBLISH_LISTING\)/,
    'Listing removed photos must use the same unit authorization as gallery management.');
assert.match(photoService, /\/v1\/units\/\$\{uuid\}\/photos\/deleted/,
    'The media service must load removed unit photos.');
assert.match(photoService, /\/v1\/units\/\$\{uuid\}\/photos\/\$\{photoUuid\}\/restore/,
    'The media service must call the existing restore operation.');
assert.match(photoGallery, /fetchDeletedUnitPhotos\(entityUuid\)/,
    'The unit photo gallery must display recoverable photos.');
assert.match(photoGallery, /restoreUnitPhoto\(entityUuid, photo\.deleteId\)/,
    'The unit photo gallery must connect its restore action to the API.');

console.log('Réussi : 9 contrôles de parité des échéances et photos d’unité.');
