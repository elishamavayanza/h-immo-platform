#!/usr/bin/env python3
"""Génère la présentation administrative générale de Soft-IMMO."""

from pathlib import Path
from shutil import copyfile
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'assets/react/app/public/documents/presentation-generale-soft-immo.pdf'
SERVER_OUTPUT = ROOT / 'public/documents/presentation-generale-soft-immo.pdf'
FONT_DIR = Path('/usr/share/fonts/truetype/msttcorefonts')

for name, filename in (
    ('TimesNewRoman', 'Times_New_Roman.ttf'),
    ('TimesNewRoman-Bold', 'Times_New_Roman_Bold.ttf'),
    ('TimesNewRoman-Italic', 'Times_New_Roman_Italic.ttf'),
    ('TimesNewRoman-BoldItalic', 'Times_New_Roman_Bold_Italic.ttf'),
):
    pdfmetrics.registerFont(TTFont(name, str(FONT_DIR / filename)))

pdfmetrics.registerFontFamily(
    'TimesNewRoman',
    normal='TimesNewRoman',
    bold='TimesNewRoman-Bold',
    italic='TimesNewRoman-Italic',
    boldItalic='TimesNewRoman-BoldItalic',
)

INK = colors.HexColor('#252b30')
SLATE = colors.HexColor('#46545d')
MUTED = colors.HexColor('#68747a')
RULE = colors.HexColor('#c8ced1')
PALE = colors.HexColor('#f2f4f4')
WHITE = colors.white
PAGE_WIDTH, PAGE_HEIGHT = A4
LEFT = RIGHT = 22 * mm
TOP = 22 * mm
BOTTOM = 21 * mm


class PresentationDoc(BaseDocTemplate):
    def __init__(self, filename: str):
        super().__init__(filename, pagesize=A4, title='Guide général de Soft-IMMO et de ses calculs',
                         author='Soft-GUIDE ACADEMY', subject='Guide administratif de présentation et de fonctionnement')
        cover = Frame(LEFT, BOTTOM, PAGE_WIDTH - LEFT - RIGHT, PAGE_HEIGHT - TOP - BOTTOM,
                      id='cover', leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        body = Frame(LEFT, BOTTOM, PAGE_WIDTH - LEFT - RIGHT, PAGE_HEIGHT - TOP - BOTTOM,
                     id='body', leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates([
            PageTemplate(id='Cover', frames=cover, onPage=self.draw_cover),
            PageTemplate(id='Body', frames=body, onPage=self.draw_body),
        ])

    @staticmethod
    def draw_cover(canvas, doc):
        canvas.saveState()
        canvas.setTitle('Soft-IMMO - Guide général et calculs')
        canvas.setAuthor('Soft-GUIDE ACADEMY')
        canvas.restoreState()

    @staticmethod
    def draw_body(canvas, doc):
        canvas.saveState()
        canvas.setStrokeColor(RULE)
        canvas.setLineWidth(0.55)
        canvas.line(LEFT, PAGE_HEIGHT - 15 * mm, PAGE_WIDTH - RIGHT, PAGE_HEIGHT - 15 * mm)
        canvas.setFont('TimesNewRoman', 9)
        canvas.setFillColor(MUTED)
        canvas.drawString(LEFT, PAGE_HEIGHT - 12 * mm, 'SOFT-IMMO  |  PRÉSENTATION GÉNÉRALE')
        canvas.line(LEFT, 15 * mm, PAGE_WIDTH - RIGHT, 15 * mm)
        canvas.drawString(LEFT, 10 * mm, 'Document administratif  -  Soft-GUIDE ACADEMY')
        canvas.drawRightString(PAGE_WIDTH - RIGHT, 10 * mm, f'Page {doc.page}')
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if not isinstance(flowable, Paragraph):
            return
        style_name = flowable.style.name
        if style_name not in ('SectionTitle', 'SubsectionTitle'):
            return
        level = 0 if style_name == 'SectionTitle' else 1
        text = flowable.getPlainText()
        key = f'heading-{self.page}-{len(text)}-{abs(hash(text))}'
        self.canv.bookmarkPage(key)
        self.canv.addOutlineEntry(text, key, level=level, closed=False)
        # La table des matières reste courte et lisible ; les sous-sections
        # restent disponibles dans les signets du lecteur PDF.
        if level == 0:
            self.notify('TOCEntry', (level, text, self.page, key))


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name='CoverKicker', fontName='TimesNewRoman-Bold', fontSize=10, leading=14,
    textColor=SLATE, alignment=TA_CENTER, spaceAfter=18,
))
styles.add(ParagraphStyle(
    name='CoverTitle', fontName='TimesNewRoman-Bold', fontSize=27, leading=32,
    textColor=INK, alignment=TA_CENTER, spaceAfter=15,
))
styles.add(ParagraphStyle(
    name='CoverSubtitle', fontName='TimesNewRoman', fontSize=14, leading=20,
    textColor=SLATE, alignment=TA_CENTER, spaceAfter=18,
))
styles.add(ParagraphStyle(
    name='CoverMeta', fontName='TimesNewRoman', fontSize=11.5, leading=17,
    textColor=MUTED, alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name='SectionTitle', fontName='TimesNewRoman-Bold', fontSize=18, leading=23,
    textColor=INK, spaceBefore=7, spaceAfter=11, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name='SubsectionTitle', fontName='TimesNewRoman-Bold', fontSize=14, leading=18,
    textColor=SLATE, spaceBefore=11, spaceAfter=6, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name='BodyTextFR', fontName='TimesNewRoman', fontSize=11.5, leading=16,
    textColor=INK, alignment=TA_LEFT, spaceAfter=7, allowWidows=0, allowOrphans=0,
))
styles.add(ParagraphStyle(
    name='BodyCompactFR', parent=styles['BodyTextFR'], fontSize=10.5, leading=14,
    spaceAfter=4,
))
styles.add(ParagraphStyle(
    name='BulletFR', parent=styles['BodyTextFR'], leftIndent=14, firstLineIndent=-9,
    bulletIndent=0, spaceAfter=4,
))
styles.add(ParagraphStyle(
    name='TableHeadFR', fontName='TimesNewRoman-Bold', fontSize=10.5, leading=13,
    textColor=INK, spaceAfter=0,
))
styles.add(ParagraphStyle(
    name='TableCellFR', fontName='TimesNewRoman', fontSize=10.2, leading=13,
    textColor=INK, spaceAfter=0,
))
styles.add(ParagraphStyle(
    name='TocTitle', fontName='TimesNewRoman-Bold', fontSize=18, leading=23,
    textColor=INK, spaceAfter=14,
))
styles.add(ParagraphStyle(
    name='TOCLevel0', fontName='TimesNewRoman-Bold', fontSize=11.5, leading=18,
    leftIndent=0, firstLineIndent=0, textColor=INK, spaceBefore=4,
))
styles.add(ParagraphStyle(
    name='TOCLevel1', fontName='TimesNewRoman', fontSize=10.5, leading=15,
    leftIndent=16, firstLineIndent=0, textColor=SLATE,
))


def para(text: str, style='BodyTextFR') -> Paragraph:
    return Paragraph(text, styles[style])


def bullets(items):
    return [Paragraph(escape(item), styles['BulletFR'], bulletText='•') for item in items]


def heading(text: str, level=0):
    return para(escape(text), 'SectionTitle' if level == 0 else 'SubsectionTitle')


def section_title(text):
    return heading(text, 0)


def build_story():
    story = []
    story += [Spacer(1, 44 * mm), para('DOCUMENT ADMINISTRATIF · ÉDITION 2.1', 'CoverKicker')]
    story += [para('SOFT-IMMO', 'CoverTitle'), para('Guide général de l’application et de ses calculs', 'CoverSubtitle')]
    story += [Spacer(1, 4 * mm)]
    rule = Table([['']], colWidths=[74 * mm], rowHeights=[1])
    rule.setStyle(TableStyle([('LINEBELOW', (0, 0), (-1, -1), 0.8, SLATE), ('ALIGN', (0, 0), (-1, -1), 'CENTER')]))
    story += [rule, Spacer(1, 17 * mm)]
    story += [para('Objet du document', 'CoverKicker')]
    story += [para('Expliquer le fonctionnement, les usages, les rôles, les indicateurs et les calculs actuellement réalisés par Soft-IMMO, ainsi que les limites à connaître.', 'CoverMeta')]
    story += [Spacer(1, 37 * mm)]
    story += [para('<b>Élaboré par</b>', 'CoverMeta'), Spacer(1, 2 * mm), para('SOFT-GUIDE ACADEMY', 'CoverKicker')]
    story += [para('Version 2.1 · 20 octobre 2026', 'CoverMeta'), NextPageTemplate('Body'), PageBreak()]

    story += [para('Table des matières', 'TocTitle')]
    toc = TableOfContents()
    toc.levelStyles = [styles['TOCLevel0'], styles['TOCLevel1']]
    toc.dotsMinLevel = 0
    story += [toc, PageBreak()]

    story += [section_title('1. Objet et identité du document'),
              para('Soft-IMMO est une application web de gestion immobilière destinée aux organisations qui administrent des villes, des parcelles, des bâtiments, des unités locatives et les opérations associées.'),
              para('Ce document présente le service, son périmètre actuel et ses limites. Il s’adresse aux responsables d’organisation, aux équipes opérationnelles, aux utilisateurs de la plateforme et aux partenaires qui doivent comprendre ce que l’application permet réellement.'),
              para('L’application et ce document sont présentés par <b>Soft-GUIDE ACADEMY</b>. Cette mention identifie l’auteur indiqué pour le document ; elle ne constitue pas une affirmation de certification, d’agrément public ou de statut juridique particulier.')]

    story += [section_title('2. À quoi sert Soft-IMMO ?'),
              para('Soft-IMMO rassemble dans un même espace les informations immobilières et locatives d’une organisation. Il aide à structurer le patrimoine, suivre les occupants et les baux, préparer les échéances, enregistrer les paiements reçus, consulter les mouvements de trésorerie et organiser certaines interventions.'),
              para('L’application est multi-organisation : plusieurs organisations peuvent utiliser la même plateforme sans partager leurs données métier. Au sein d’une organisation, les accès peuvent aussi être limités aux villes attribuées à un ADMIN_VILLE.'),
              para('Son objectif est d’améliorer le suivi et la traçabilité des opérations. Les données saisies et leur exactitude restent sous la responsabilité des personnes autorisées qui les enregistrent.')]

    story += [section_title('3. Comment l’application organise le travail'),
              para('Les informations immobilières suivent une hiérarchie de navigation : organisation, ville, parcelle, bâtiment, unité. Une unité peut être liée à un bail ; le bail est associé à un locataire et peut produire des échéances de loyer. Les paiements reçus peuvent être enregistrés sur ces échéances.'),
              para('Les comptes reçoivent un rôle dans une organisation. Le rôle définit les écrans et opérations disponibles ; l’organisation active et, pour l’ADMIN_VILLE, les villes affectées délimitent les données auxquelles le compte peut accéder.'),
              para('Les opérations de création, de modification ou de consultation sont contrôlées par le serveur. L’interface masque les actions indisponibles, mais la sécurité ne dépend pas seulement de l’affichage : l’API vérifie aussi les droits et le périmètre de chaque demande.')]

    story += [section_title('4. Ce que Soft-IMMO permet de faire'),
              heading('4.1 Administrer les organisations et les accès', 1),
              para('Le rôle SUPER_ADMIN crée et administre les organisations, configure leurs capacités, gère l’option Vitrine, supervise les comptes, consulte le journal d’audit et diffuse les publications de plateforme. Les capacités peuvent fixer des plafonds de villes, parcelles, bâtiments, unités et membres actifs.'),
              heading('4.2 Décrire et gérer le patrimoine', 1),
              para('Les rôles autorisés peuvent organiser le patrimoine par ville, parcelle, bâtiment et unité, préciser les niveaux et certains accès, gérer les informations descriptives et, si la Vitrine est autorisée, publier les unités prévues à cet effet.'),
              heading('4.3 Suivre les locataires et les locations', 1),
              para('Les écrans locatifs permettent de tenir les dossiers des locataires, consigner les baux et suivre les périodes d’occupation, les échéances et les mouvements de location. Des vues et filtres facilitent le suivi de l’activité et des situations à traiter.'),
              heading('4.4 Enregistrer et suivre les finances opérationnelles', 1),
              para('Les utilisateurs autorisés peuvent consulter les loyers, enregistrer des paiements effectivement reçus et suivre des dépenses et mouvements de trésorerie. Les montants conservent leur devise ; les montants en USD et en CDF ne doivent pas être additionnés comme s’ils étaient dans une seule devise.'),
              heading('4.5 Organiser le personnel et les interventions', 1),
              para('Selon le rôle, l’application permet de gérer des fiches de personnel, certaines affectations, et des interventions de maintenance rattachées à une ville ou à un bien. Les statuts permettent de suivre l’avancement des interventions.'),
              heading('4.6 Consulter les rapports, l’aide et la Vitrine', 1),
              para('Les rapports présentent des synthèses calculées selon la période et les filtres choisis. Le manuel et les aides contextuelles guident l’utilisateur. La Vitrine peut présenter des informations publiques autorisées par la plateforme ; sa disponibilité dépend de l’activation de l’organisation.'),
              heading('4.7 Tableau de bord', 1),
              para('Le tableau de bord est une vue rapide adaptée au rôle : il rassemble des indicateurs récents ou synthétiques et des liens vers les pages de travail. Il ne remplace pas la page Rapports, qui permet de consulter des détails, périodes et ventilations. Un indicateur du tableau de bord reste fondé sur les données effectivement enregistrées.'),
              heading('4.8 Locataires, baux, loyers et paiements', 1),
              para('La page Locataires présente les dossiers et permet d’ouvrir les baux associés à une personne ou une entreprise. La page Loyers offre un suivi transversal des échéances, y compris les échéances partiellement réglées et en retard. Les paiements sont accessibles à partir d’une échéance afin de conserver le lien entre la somme reçue et le montant attendu. Les mouvements de départ, transfert ou changement d’occupant suivent un parcours dédié lorsqu’il est proposé.'),
              heading('4.9 Trésorerie et dépenses', 1),
              para('La page Trésorerie réunit la gestion des dépenses, leurs catégories, leurs justificatifs ou références, et le suivi de caisse par ville et devise. Une dépense peut viser une seule cible immobilière ; son annulation crée une contre-écriture négative au lieu de supprimer l’originale. Le solde espèces présenté repose sur un solde d’ouverture et les seuls mouvements en espèces.'),
              heading('4.10 Administration et gestion de l’équipe', 1),
              para('L’espace Administration permet aux rôles autorisés de consulter l’équipe, inviter des membres, attribuer un rôle organisationnel et, pour les fonctions qui le permettent, affecter un ADMIN_VILLE à ses villes. Le PATRON administre les accès de son organisation selon les règles de la plateforme. L’ADMIN_IMMOBILIER peut gérer le patrimoine et le personnel dans le périmètre accordé, mais l’administration générale de l’organisation reste réservée aux rôles habilités.'),
              heading('4.11 Personnel, opérations et maintenance', 1),
              para('La page Personnel regroupe les fiches des travailleurs et leurs affectations pour les rôles qui y ont accès. Pour un ADMIN_VILLE, les affectations sont présentées dans Opérations et restent bornées aux villes autorisées. La page Maintenance sert à consigner et suivre les demandes ou travaux ; elle n’est ni un système de diagnostic technique ni une commande automatique auprès d’un prestataire.'),
              heading('4.12 Publications, audit et paramètres', 1),
              para('Les Publications sont gérées par le SUPER_ADMIN et servent aux communications de plateforme destinées aux utilisateurs. Le journal d’audit présente les opérations sensibles accessibles au rôle courant. Dans Paramètres, l’utilisateur peut consulter son profil, préférences et ressources d’aide. Le manuel de chaque rôle explique les menus visibles et les étapes courantes ; le présent guide général est proposé séparément à tous les rôles.')]

    story += [section_title('5. Rôles et responsabilités'),
              para('Les droits précis varient selon la fonction, l’organisation active et les villes attribuées. Le tableau ci-dessous résume les responsabilités principales ; il ne remplace pas la matrice d’autorisations appliquée par l’application.')]
    rows = [[Paragraph('Rôle', styles['TableHeadFR']), Paragraph('Responsabilité générale', styles['TableHeadFR'])]]
    roles = [
        ('SUPER_ADMIN', 'Administration de la plateforme : organisations, capacités, utilisateurs, publications, audit et rapports plateforme. Ce rôle ne donne pas automatiquement accès aux opérations métier d’une organisation.'),
        ('PATRON', 'Pilotage de son organisation, consultation consolidée et gestion de l’équipe et des accès permis. Les opérations quotidiennes locales sont effectuées par les responsables opérationnels autorisés.'),
        ('ADMIN_IMMOBILIER', 'Gestion du patrimoine de son organisation et des fonctions de gestion immobilière ou de personnel prévues pour son rôle.'),
        ('ADMIN_VILLE', 'Gestion opérationnelle limitée aux villes qui lui sont attribuées : patrimoine, locataires, loyers, dépenses ou interventions selon les droits actifs.'),
    ]
    for role, desc in roles:
        rows.append([Paragraph(f'<b>{escape(role)}</b>', styles['TableCellFR']), Paragraph(escape(desc), styles['TableCellFR'])])
    table = Table(rows, colWidths=[45 * mm, 121 * mm], repeatRows=1, hAlign='LEFT')
    table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PALE), ('TEXTCOLOR', (0, 0), (-1, -1), INK),
        ('GRID', (0, 0), (-1, -1), 0.45, RULE), ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 7), ('RIGHTPADDING', (0, 0), (-1, -1), 7),
        ('TOPPADDING', (0, 0), (-1, -1), 7), ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
    ]))
    story += [table, Spacer(1, 5 * mm),
              para('Le SUPER_ADMIN attribue les rôles et les capacités de plateforme. Les responsables de l’organisation attribuent les accès d’équipe prévus pour leur fonction. L’ADMIN_VILLE ne peut pas étendre lui-même son périmètre à une ville qui ne lui a pas été attribuée.')]

    story += [section_title('6. Comprendre les calculs et indicateurs'),
              para('Les formules suivantes décrivent les principaux indicateurs produits par les services de rapport et de trésorerie. Les montants sont manipulés en décimales à deux chiffres pour les calculs monétaires ; les exemples sont fictifs et servent uniquement à expliquer les mécanismes.'),
              heading('6.1 Statut d’une échéance et solde restant', 1),
              para('Pour une échéance, Soft-IMMO additionne les paiements rattachés à cette échéance. Le solde restant est <b>montant de l’échéance − total des paiements enregistrés</b>. Le statut est « payée » si le total atteint le montant dû, « partiellement payée » si une somme positive a été payée sans solder le montant, et « en attente » si aucun paiement n’est enregistré. Une faible tolérance d’arrondi évite qu’une différence technique d’un demi-centime laisse un loyer artificiellement ouvert.'),
              para('Le statut « en retard » est calculé au moment de la consultation : la date d’exigibilité doit être strictement avant aujourd’hui et le paiement total doit rester inférieur au montant dû. Une échéance due aujourd’hui n’est donc pas encore en retard. Une échéance soldée reste payée après sa date. « En retard » n’est pas enregistré comme un statut permanent.'),
              para('<b>Exemple :</b> échéance de 300 USD, paiements enregistrés de 200 USD : solde restant de 100 USD. Elle est en retard si sa date d’exigibilité est passée ; si la date est aujourd’hui ou future, elle est partielle mais pas encore en retard.'),
              heading('6.2 Taux d’occupation', 1),
              para('Le taux est <b>unités occupées par un bail actif ÷ unités recensées × 100</b>, arrondi à deux décimales. Une unité est occupée si un bail actif la désigne comme unité principale ou additionnelle. Les unités ainsi que les bâtiments et parcelles supprimés logiquement sont exclus. Si aucune unité n’est comptée, le taux renvoyé est 0 %.'),
              para('<b>Exemple :</b> 18 unités occupées sur 24 donnent 18 ÷ 24 × 100 = <b>75 %</b>. Cet indicateur mesure les baux actifs enregistrés, pas nécessairement l’occupation physique constatée sur le terrain.'),
              heading('6.3 Loyers attendus, recettes et résultat net', 1),
              para('Les loyers attendus d’une période sont la somme des échéances rattachées à des baux actifs, classées par mois et devise. Les recettes sont la somme des paiements dont la date de paiement appartient à la période. Les dépenses sont la somme des dépenses dont la date appartient à la période. Le résultat net du rapport est <b>recettes enregistrées − dépenses enregistrées</b>.'),
              para('Loyer attendu et recette encaissée ne suivent pas la même date : l’un se rattache à la période de l’échéance, l’autre au jour du paiement. Le résultat net n’est ni un bénéfice certifié, ni un solde de caisse, et il ne constitue pas une comptabilité générale.'),
              para('<b>Exemple :</b> sur un mois, 1 200 USD de paiements et 350 USD de dépenses enregistrés donnent un résultat net de 850 USD, avant les éléments hors de Soft-IMMO.'),
              heading('6.4 Impayés et jours de retard', 1),
              para('Pour chaque échéance incluse dans les impayés, le montant indiqué est <b>montant dû − paiements enregistrés</b>. Les rapports incluent les échéances en retard ou partiellement payées. Le nombre de jours est calculé entre la date d’exigibilité et la date de référence lorsque la première est antérieure ; sinon il vaut 0.'),
              para('Une échéance partielle peut apparaître dans cette liste même si sa date d’exigibilité est aujourd’hui ou dans le futur. Il faut donc examiner ensemble le solde, le statut et la date avant de qualifier une somme de retard exigible.'),
              heading('6.5 Conversion des devises dans les rapports', 1),
              para('Les paiements et dépenses conservent leur devise d’origine (USD ou CDF). Certaines synthèses convertissent les montants vers la devise de référence de l’organisation avec le taux historique applicable au mois de l’opération. Un taux exprime « 1 devise de base = X unités de devise cotée » : la conversion directe multiplie par le taux ; avec un taux inverse seul, le système divise par ce taux. La recherche privilégie le taux de ville autorisé, puis le taux d’organisation, puis le taux de plateforme.'),
              para('Si aucun taux direct ou inverse n’existe, le rapport conserve le montant dans sa devise d’origine au lieu de bloquer. Une synthèse peut alors additionner des valeurs qui ne sont pas converties dans une devise commune. Il est essentiel de vérifier les taux historiques avant d’interpréter un rapport multi-devise.'),
              para('<b>Exemple fictif :</b> avec un taux de 1 USD = 2 850 CDF, 100 USD deviennent 285 000 CDF. Ce chiffre illustre la formule et ne représente pas un taux de marché.'),
              heading('6.6 Solde de caisse espèces', 1),
              para('Le solde de caisse courant d’une ville et d’une devise suit <b>solde d’ouverture + paiements en espèces − dépenses en espèces</b>, à partir de la date d’ouverture incluse. Les autres méthodes de paiement ne sont pas incluses. Sans solde d’ouverture, aucun solde calculé n’est fourni.'),
              para('<b>Exemple :</b> ouverture de 1 000 000 CDF, encaissements espèces de 250 000 CDF, dépenses espèces de 180 000 CDF : solde calculé de 1 070 000 CDF. Ce résultat dépend de saisies complètes et ne remplace pas un comptage physique.'),
              heading('6.7 Alertes de fin de bail et évolution', 1),
              para('Les alertes listent les baux actifs dont la fin est située entre aujourd’hui et les 30 prochains jours inclus. Elles servent de repère et ne renouvellent pas le bail. Les vues d’évolution comparent les unités occupées par bail actif à des dates de référence de la période choisie.'),
              heading('6.8 Plafonds de capacité', 1),
              para('Les plafonds configurés par le SUPER_ADMIN concernent séparément les villes, parcelles, bâtiments, unités et membres actifs. Pour une ressource, une création est refusée lorsque <b>usage actuel ≥ plafond</b>. Un plafond non configuré n’impose pas de limite. Atteindre un plafond n’efface pas les enregistrements existants.'),
              heading('6.9 Périodes et horodatage', 1),
              para('Les rapports d’organisation prennent par défaut l’année civile courante, sauf période choisie par l’utilisateur. Les synthèses financières sont regroupées par mois. Le stockage et les calculs temporels utilisent UTC ; le fuseau Africa/Kinshasa ne sert qu’à la présentation lorsque celle-ci le demande.'),
              heading('6.10 Règles locatives configurables et calcul des échéances', 1),
              para('Les règles locatives sont désormais configurables par organisation et peuvent être remplacées explicitement dans un bail. Le calcul partagé par la création manuelle et la génération automatique résout chaque paramètre dans cet ordre : surcharge valide du bail, version d’organisation publiée applicable au premier jour de la période facturée, puis défaut Soft-IMMO. Une valeur absente n’est pas interprétée comme un choix explicite et le texte libre des clauses du bail n’est pas analysé automatiquement.'),
              para('Une règle générale définit le paiement d’avance ou à terme échu, le jour habituel d’exigibilité et le traitement du premier et du dernier mois partiels. Une date d’effet doit être le premier jour du mois. Les versions publiées sont immuables ; publier une nouvelle règle ne modifie pas les échéances déjà émises ni leurs paiements. Un snapshot de chaque nouvelle échéance conserve les valeurs et sources résolues, la période, la devise et la version du calculateur.'),
              para('<b>Exemple :</b> pour un loyer mensuel de 100 USD couvrant 1–19 octobre sur un mois de 31 jours, le prorata reste 100 × 19/31 = <b>61,29 USD</b>. Avec un jour habituel 5 et la règle qui borne le jour régulier dans la couverture, la date peut être le 5 octobre ; avec la règle « fin de la période couverte », elle est le 19 octobre. La date d’exigibilité change, mais pas la période ni le montant. Pour le bail du 20 octobre 2026 au 19 octobre 2027, les proratas de référence sont 38,71 USD pour les 12 jours du premier mois et 61,29 USD pour les 19 jours du dernier mois.'),
              para('Le prorata USD utilise les jours calendaires réels et un arrondi au centime demi au supérieur, sans nombres flottants. Le prorata partiel CDF reste bloqué jusqu’à l’approbation de sa précision et de son arrondi. La génération est idempotente : elle ne crée que les périodes manquantes, ne crée jamais de paiement et ne recalcule pas une échéance existante. L’aperçu du bail montre la période, le montant, la date et la provenance des règles sans persister ces résultats.'),
              para('Le PATRON peut préparer, publier et déléguer la publication. L’ADMIN_IMMOBILIER peut préparer un brouillon et publier uniquement si une délégation active l’y autorise. L’ADMIN_VILLE consulte les règles d’organisation publiées et intervient sur les baux de ses villes selon les permissions actives. Les contrôles d’autorisation sont effectués côté serveur et les publications sont auditées.'),
              section_title('7. Exemples de parcours de travail'),
              heading('7.1 Préparer une organisation', 1),
              para('Le SUPER_ADMIN crée l’organisation, fixe ses capacités et active éventuellement la Vitrine. Les responsables recensent ensuite les villes, créent les accès de l’équipe et attribuent uniquement les rôles et villes nécessaires.'),
              heading('7.2 Mettre un bien en location', 1),
              para('L’équipe crée la ville, la parcelle, le bâtiment et l’unité, puis vérifie les références et la devise. Elle sélectionne ou crée le locataire, saisit le bail, ses dates et unités, puis crée les échéances prévues. Après réception réelle d’un paiement, un utilisateur autorisé l’enregistre sur la bonne échéance et vérifie le solde recalculé.'),
              heading('7.3 Corriger une opération', 1),
              para('Avant toute correction, il faut vérifier le justificatif, le compte, la période, le montant et la devise. Lorsqu’une opération est annulable, l’application conserve l’écriture initiale et crée une contre-écriture opposée qui lui est reliée. Cela ajuste les totaux sans effacer silencieusement l’historique. Une contre-écriture ne peut pas être annulée par la même action.'),
              section_title('8. Lire les rapports avec prudence'),
              para('Un indicateur dépend de son périmètre, de la période, de la ville, de la devise et des filtres appliqués. Avant de comparer deux rapports, vérifiez qu’ils portent sur les mêmes périmètres. Une recette enregistrée ce mois-ci peut correspondre à une échéance antérieure ; une hausse des encaissements ne signifie pas nécessairement que le loyer attendu du mois a augmenté.'),
              para('Les indicateurs de recettes, dépenses, impayés, caisse et occupation sont des outils de suivi. Ils ne remplacent pas les contrats, reçus, relevés, justificatifs et inventaires. Les rapports ne peuvent pas deviner une donnée absente ou corriger une mauvaise date, devise, unité ou ville.'),
              section_title('9. Ce que Soft-IMMO ne fait pas'),
              para('Le périmètre fonctionnel actuel doit être compris sans supposer des services externes ou des automatismes qui ne sont pas présents dans l’application :')]
    story += bullets([
        'Soft-IMMO ne réalise pas de paiement bancaire en ligne, ne débite pas un compte et ne remplace pas une banque ou une passerelle de paiement. Un utilisateur peut enregistrer dans l’application un paiement reçu par un autre moyen.',
        'L’application ne remplace pas un logiciel complet de comptabilité générale, un service fiscal, un système de facturation réglementaire ou un audit comptable indépendant.',
        'Elle ne certifie pas la propriété d’un terrain ou d’un immeuble, ne vérifie pas les titres auprès d’un registre public et ne fournit pas une évaluation immobilière officielle.',
        'La saisie d’un bail dans l’application ne constitue pas à elle seule une signature électronique, un avis juridique ou une garantie de conformité à une législation locale.',
        'La gestion de personnel et des affectations ne constitue pas un système de paie ou de calcul automatique des salaires.',
        'La Vitrine ne s’active pas automatiquement pour toutes les organisations ; son autorisation dépend du SUPER_ADMIN et les éléments visibles dépendent de leur configuration.',
        'Les rapports décrivent les données disponibles et les filtres appliqués. Ils ne corrigent pas automatiquement une saisie incomplète ou incorrecte et ne remplacent pas les justificatifs originaux.',
        'L’installation de l’application ou la création d’un raccourci dépend du navigateur et de l’appareil. Il ne faut pas considérer l’application comme un système garantissant le travail hors connexion.'
    ])

    story += [section_title('10. Sécurité, confidentialité et qualité des données'),
              para('L’accès aux données est conçu autour de l’organisation et du rôle actif. Les informations d’une organisation ne doivent pas être consultables par les membres d’une autre organisation ; le périmètre d’un ADMIN_VILLE est limité aux villes qui lui sont attribuées. Les contrôles sont appliqués côté serveur.'),
              para('Les utilisateurs doivent se connecter avec leur propre compte, protéger leurs identifiants, vérifier l’organisation et la ville actives avant une saisie et ne partager que les informations nécessaires à leur fonction. Un refus d’accès doit être signalé à l’administrateur compétent, jamais contourné.'),
              para('Les actions sensibles prévues par l’application peuvent être consignées dans un journal d’audit. Les utilisateurs doivent vérifier les noms, dates, montants, devises, références et liens entre biens avant de confirmer une opération. Une correction financière doit respecter le mécanisme prévu afin de conserver la trace comptable.')]

    story += [section_title('11. Conditions et limites d’utilisation'),
              para('Soft-IMMO est une application web : l’accès aux données et l’enregistrement des opérations nécessitent un compte actif, les autorisations correspondantes et une connexion au service. Les actions visibles peuvent varier en fonction du rôle, de l’organisation, des capacités configurées et des fonctionnalités activées.'),
              para('Les plafonds de capacité sont configurés manuellement par le SUPER_ADMIN. Lorsqu’un plafond est atteint, l’application peut refuser les nouvelles créations concernées ; les éléments déjà enregistrés ne sont pas supprimés par ce seul fait. Les organisations doivent contacter leur responsable de plateforme pour demander une adaptation.'),
              para('Le fonctionnement opérationnel dépend de la disponibilité de l’infrastructure, de la qualité des données saisies et des procédures internes de l’organisation. Ce document décrit le périmètre fonctionnel général et ne vaut pas contrat de service, engagement de disponibilité ou conseil professionnel.')]

    story += [section_title('12. Glossaire et résumé administratif'),
              para('<b>Organisation :</b> espace de travail qui regroupe des villes, des membres et des données métier.'),
              para('<b>Unité :</b> bien ou espace locatif rattaché à un bâtiment et susceptible d’être couvert par un bail.'),
              para('<b>Bail actif :</b> bail dont le statut est actif ; il sert notamment au calcul d’occupation.'),
              para('<b>Échéance :</b> montant attendu pour une période et une date d’exigibilité.'),
              para('<b>Recette :</b> paiement enregistré comme reçu ; elle suit la date et la devise saisies.'),
              para('<b>Solde restant :</b> différence entre le montant de l’échéance et les paiements qui lui sont associés.'),
              para('<b>Devise de référence :</b> devise de présentation des synthèses après conversion historique lorsqu’un taux est disponible.'),
              para('<b>Contre-écriture :</b> opération de correction opposée, liée à l’écriture initiale pour conserver sa trace.'),
              para('<b>Capacité :</b> plafond de création défini pour une ressource d’une organisation.'),
              para('<b>Soft-IMMO</b> est une plateforme web multi-organisation conçue pour structurer et suivre le patrimoine immobilier et les activités locatives associées. Elle fournit des outils de gestion, de consultation et de traçabilité selon les rôles attribués.'),
              para('Elle aide les équipes à travailler à partir d’informations centralisées, mais ne remplace ni les institutions financières, ni les professionnels du droit ou de la comptabilité, ni la vérification des documents originaux. L’organisation reste responsable de ses décisions, de ses justificatifs et de la qualité des données.'),
              Spacer(1, 4 * mm),
              para('<b>Document préparé par Soft-GUIDE ACADEMY</b><br/>Guide général de l’application et de ses calculs · Version 2.1 · 20 octobre 2026', 'BodyCompactFR')]
    return story


def main():
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc = PresentationDoc(str(OUTPUT))
    story = build_story()
    doc.multiBuild(story)
    SERVER_OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    copyfile(OUTPUT, SERVER_OUTPUT)
    print(f'{OUTPUT} ({OUTPUT.stat().st_size} octets)')
    print(f'{SERVER_OUTPUT} (copie de déploiement)')


if __name__ == '__main__':
    main()
