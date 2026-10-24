#!/usr/bin/env python3
"""Génère les guides PDF textuels depuis le catalogue partagé avec le web."""
from __future__ import annotations

import json
import shutil
import subprocess
import tempfile
from pathlib import Path

from pypdf import PdfReader
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Flowable,
    Frame,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents

ROOT = Path(__file__).resolve().parents[1]
CATALOG = ROOT / 'docs/user-manuals/catalog.json'
OUT = ROOT / 'output/pdf'
LOGO = ROOT / 'tools/assets/soft-immo-logo-pdf.png'
DATA = json.loads(CATALOG.read_text(encoding='utf-8'))

FONT_DIR = Path('/usr/share/fonts/truetype/msttcorefonts')
pdfmetrics.registerFont(TTFont('TimesNewRoman', str(FONT_DIR / 'Times_New_Roman.ttf')))
pdfmetrics.registerFont(TTFont('TimesNewRoman-Bold', str(FONT_DIR / 'Times_New_Roman_Bold.ttf')))
pdfmetrics.registerFont(TTFont('TimesNewRoman-Italic', str(FONT_DIR / 'Times_New_Roman_Italic.ttf')))
pdfmetrics.registerFont(TTFont('TimesNewRoman-BoldItalic', str(FONT_DIR / 'Times_New_Roman_Bold_Italic.ttf')))
pdfmetrics.registerFontFamily(
    'TimesNewRoman',
    normal='TimesNewRoman',
    bold='TimesNewRoman-Bold',
    italic='TimesNewRoman-Italic',
    boldItalic='TimesNewRoman-BoldItalic',
)

INK = colors.HexColor('#263640')
TEAL = colors.HexColor('#287889')
MUTED = colors.HexColor('#647780')
PALE = colors.HexColor('#edf2f1')
LINE = colors.HexColor('#d1dcdd')
GREEN = colors.HexColor('#287554')
GREEN_PALE = colors.HexColor('#eaf4ee')
RED = colors.HexColor('#a3424f')
RED_PALE = colors.HexColor('#f8eded')
AMBER = colors.HexColor('#8a6422')
AMBER_PALE = colors.HexColor('#f7f1e4')

styles = getSampleStyleSheet()
styles.add(ParagraphStyle('CoverTitle', fontName='TimesNewRoman-Bold', fontSize=27, leading=33, textColor=INK, alignment=TA_CENTER, spaceAfter=12))
styles.add(ParagraphStyle('CoverSub', fontName='TimesNewRoman', fontSize=14, leading=20, textColor=MUTED, alignment=TA_CENTER))
styles.add(ParagraphStyle('ManualH1', fontName='TimesNewRoman-Bold', fontSize=18, leading=23, textColor=INK, spaceBefore=10, spaceAfter=8, keepWithNext=True))
styles.add(ParagraphStyle('ManualH2', fontName='TimesNewRoman-Bold', fontSize=13, leading=17, textColor=TEAL, spaceBefore=8, spaceAfter=5, keepWithNext=True))
styles.add(ParagraphStyle('ManualH3', fontName='TimesNewRoman-Bold', fontSize=11.5, leading=15, textColor=INK, spaceBefore=5, spaceAfter=4, keepWithNext=True))
styles.add(ParagraphStyle('ManualBody', fontName='TimesNewRoman', fontSize=11, leading=15, textColor=INK, spaceAfter=6))
styles.add(ParagraphStyle('ManualSmall', fontName='TimesNewRoman', fontSize=9, leading=12, textColor=MUTED, spaceAfter=4))
styles.add(ParagraphStyle('ManualCaption', fontName='TimesNewRoman-Italic', fontSize=9, leading=12, textColor=MUTED, alignment=TA_CENTER, spaceBefore=5, spaceAfter=9))
styles.add(ParagraphStyle('TOC1', fontName='TimesNewRoman', fontSize=11, leading=15, textColor=INK, leftIndent=0, firstLineIndent=0, spaceBefore=4))
styles.add(ParagraphStyle('TOC2', fontName='TimesNewRoman', fontSize=9.5, leading=13, textColor=MUTED, leftIndent=14, firstLineIndent=0, spaceBefore=2))
styles.add(ParagraphStyle('SignalTitle', fontName='TimesNewRoman-Bold', fontSize=10.5, leading=13, textColor=INK, spaceAfter=3))
styles.add(ParagraphStyle('SignalBody', fontName='TimesNewRoman', fontSize=10.5, leading=14, textColor=INK))


class VectorSignal(Flowable):
    """Icônes vectorielles nettes, dessinées dans le PDF sans capture d’écran."""

    def __init__(self, kind: str):
        super().__init__()
        self.kind = kind
        self.width = 8 * mm
        self.height = 8 * mm

    def draw(self) -> None:
        canvas = self.canv
        canvas.saveState()
        canvas.setStrokeColor({'success': GREEN, 'error': RED, 'note': TEAL, 'action': TEAL}[self.kind])
        canvas.setLineWidth(1.5)
        cx, cy, radius = self.width / 2, self.height / 2, 3.1 * mm
        if self.kind == 'error':
            path = canvas.beginPath()
            path.moveTo(cx, cy + radius)
            path.lineTo(cx + radius, cy - radius)
            path.lineTo(cx - radius, cy - radius)
            path.close()
            canvas.drawPath(path, stroke=1, fill=0)
            canvas.line(cx, cy + 1.1 * mm, cx, cy - 0.7 * mm)
            canvas.circle(cx, cy - 1.5 * mm, .25 * mm, stroke=1, fill=1)
        else:
            canvas.circle(cx, cy, radius, stroke=1, fill=0)
            if self.kind == 'success':
                canvas.line(cx - 1.7 * mm, cy, cx - .4 * mm, cy - 1.2 * mm)
                canvas.line(cx - .4 * mm, cy - 1.2 * mm, cx + 2 * mm, cy + 1.5 * mm)
            elif self.kind == 'note':
                canvas.circle(cx, cy + 1.2 * mm, .25 * mm, stroke=1, fill=1)
                canvas.line(cx, cy + .3 * mm, cx, cy - 1.7 * mm)
            else:
                canvas.line(cx - 1.6 * mm, cy, cx + 1.6 * mm, cy)
                canvas.line(cx, cy - 1.6 * mm, cx, cy + 1.6 * mm)
        canvas.restoreState()


def signal_box(kind: str, title: str, body: str, width: float):
    """Encadré texte avec un pictogramme vectoriel et une couleur de statut."""
    background, edge = {
        'success': (GREEN_PALE, GREEN),
        'error': (RED_PALE, RED),
        'note': (PALE, TEAL),
        'action': (PALE, TEAL),
    }[kind]
    content = [Paragraph(title, styles['SignalTitle']), Paragraph(body, styles['SignalBody'])]
    table = Table([[VectorSignal(kind), content]], colWidths=[12 * mm, width - 12 * mm], hAlign='LEFT')
    table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), background),
        ('BOX', (0, 0), (-1, -1), .5, LINE),
        ('LINEBEFORE', (0, 0), (0, -1), 2.5, edge),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
        ('RIGHTPADDING', (0, 0), (-1, -1), 8),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
    ]))
    return table


def error_guidance(module: dict) -> str:
    menu = str(module['menu']).lower()
    if 'ville' in menu or 'opérations' in menu:
        context = 'Vérifiez d’abord la ville active et son attribution à votre compte.'
    else:
        context = 'Vérifiez d’abord l’espace actif et le rôle affiché dans votre compte.'
    return (
        f'{context} Relisez ensuite les champs obligatoires et le message affiché. '
        'Corrigez uniquement les éléments signalés puis réessayez. Si l’erreur persiste, '
        'notez le nom de l’écran, l’action et le message exact, sans communiquer de mot de passe ni de jeton.'
    )


def action_title(instruction: str) -> str:
    return instruction.strip().rstrip('.!?')


class ManualDoc(BaseDocTemplate):
    def __init__(self, path: Path, role: dict):
        super().__init__(
            str(path), pagesize=A4, leftMargin=21 * mm, rightMargin=21 * mm,
            topMargin=22 * mm, bottomMargin=19 * mm, title=role['title'], author='Soft-IMMO',
        )
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id='normal')
        self.addPageTemplates([PageTemplate(id='main', frames=frame, onPage=self.decorate)])

    def decorate(self, canvas, doc):
        if doc.page == 1:
            return
        canvas.saveState()
        page_width, page_height = A4
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(.5)
        canvas.line(21 * mm, page_height - 15 * mm, page_width - 21 * mm, page_height - 15 * mm)
        canvas.setFont('TimesNewRoman-Bold', 9)
        canvas.setFillColor(TEAL)
        canvas.drawString(21 * mm, page_height - 12 * mm, 'SOFT-IMMO  |  GUIDE UTILISATEUR')
        canvas.setStrokeColor(LINE)
        canvas.line(21 * mm, 14 * mm, page_width - 21 * mm, 14 * mm)
        canvas.setFont('TimesNewRoman', 9)
        canvas.setFillColor(MUTED)
        canvas.drawString(21 * mm, 9 * mm, f"Version {DATA['version']} · {DATA['updatedAt']}")
        canvas.drawRightString(page_width - 21 * mm, 9 * mm, f'Page {doc.page}')
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if not isinstance(flowable, Paragraph):
            return
        style_name = flowable.style.name
        if style_name not in {'ManualH1', 'ManualH2'}:
            return
        title = flowable.getPlainText()
        if title == 'Table des matières':
            return
        level = 0 if style_name == 'ManualH1' else 1
        key = f'h{self.page}-{abs(hash((title, level)))}'
        self.canv.bookmarkPage(key)
        self.canv.addOutlineEntry(title, key, level, False)
        self.notify('TOCEntry', (level, title, self.page, key))


def logo_flowable(width=42 * mm):
    from reportlab.platypus import Image

    return Image(str(LOGO), width=width, height=width * .72, kind='proportional')


def build_intro(role: dict) -> list:
    return [
        Paragraph('Bienvenue dans votre espace', styles['ManualH1']),
        Paragraph(role['description'], styles['ManualBody']),
        Paragraph('<b>Accès requis.</b> Connectez-vous avec un compte actif. Les menus visibles dépendent de votre rôle et, pour les rôles d’organisation, de l’organisation active et des villes qui vous sont attribuées.', styles['ManualBody']),
        Paragraph('<b>Utiliser le sommaire.</b> Le sommaire suit les catégories du menu. Chaque écran contient une liste d’actions numérotées. Sélectionnez une action pour retrouver son explication et les résultats, vérifications et erreurs associés.', styles['ManualBody']),
        Paragraph('<b>Vérifier votre contexte.</b> Avant toute opération, confirmez le rôle et l’espace actif. Le rôle PATRON et l’ADMIN_IMMOBILIER supervisent des données organisationnelles; l’ADMIN_VILLE agit uniquement dans ses villes attribuées; le SUPER_ADMIN administre la plateforme.', styles['ManualBody']),
        Paragraph('Légende des repères', styles['ManualH2']),
        signal_box('note', 'À préparer / à vérifier', 'Champs, informations ou relations à contrôler avant l’action.', 168 * mm),
        Spacer(1, 3 * mm),
        signal_box('success', 'Réussite', 'Résultat attendu après l’opération ou sur l’écran concerné.', 168 * mm),
        Spacer(1, 3 * mm),
        signal_box('error', 'Blocage / erreur', 'Pistes de vérification et informations utiles pour demander de l’aide.', 168 * mm),
        Paragraph('Sécurité et périmètre', styles['ManualH2']),
        Paragraph('Ne partagez pas votre mot de passe ni vos jetons de connexion. L’API vérifie les autorisations côté serveur. Une action absente ou refusée ne doit pas être contournée en modifiant l’adresse de la page ou les données de la requête.', styles['ManualBody']),
        PageBreak(),
    ]


def build(role_key: str, role: dict) -> Path:
    output = OUT / role['filename']
    doc = ManualDoc(output, role)
    story = [
        Spacer(1, 18 * mm), logo_flowable(), Spacer(1, 17 * mm),
        Paragraph('MANUEL D’UTILISATION', styles['CoverSub']), Spacer(1, 6 * mm),
        Paragraph(role['title'].replace('Manuel d’utilisation — ', ''), styles['CoverTitle']),
        Spacer(1, 4 * mm),
        Paragraph(role['label'], ParagraphStyle('RoleLabel', parent=styles['CoverSub'], fontName='TimesNewRoman-Bold', textColor=TEAL, fontSize=12)),
        Spacer(1, 7 * mm), Paragraph(role['description'], styles['CoverSub']), Spacer(1, 18 * mm),
    ]
    version_table = Table([
        [Paragraph(f"Version <b>{DATA['version']}</b>", styles['ManualBody']), Paragraph(f"Mise à jour <b>{DATA['updatedAt']}</b>", styles['ManualBody'])]
    ], colWidths=[75 * mm, 75 * mm])
    version_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), PALE), ('BOX', (0, 0), (-1, -1), .6, LINE),
        ('INNERGRID', (0, 0), (-1, -1), .4, LINE), ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LEFTPADDING', (0, 0), (-1, -1), 10), ('TOPPADDING', (0, 0), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ]))
    story += [
        version_table, Spacer(1, 20 * mm),
        Paragraph('Guide textuel basé sur les rubriques et les procédures du manuel web. Ce document ne contient pas de capture d’écran.', styles['ManualCaption']),
        PageBreak(), Paragraph('Table des matières', styles['ManualH1']),
    ]
    toc = TableOfContents()
    toc.levelStyles = [styles['TOC1'], styles['TOC2']]
    toc.dotsMinLevel = 0
    story += [toc, PageBreak(), *build_intro(role)]

    content_width = doc.width
    for module in role['modules']:
        story.extend([
            Paragraph(module['name'], styles['ManualH1']),
            Paragraph(f"<b>Menu :</b> {module['menu']}", styles['ManualBody']),
            Paragraph(f"<b>Objectif :</b> {module['goal']}", styles['ManualBody']),
            Paragraph('Actions de cet écran', styles['ManualH2']),
            Paragraph('Les étapes ci-dessous correspondent aux opérations décrites dans le manuel web. Les repères de préparation, réussite et erreur s’appliquent à l’ensemble des actions de cet écran.', styles['ManualBody']),
            signal_box('note', 'À préparer / à vérifier', module.get('fields') or 'Consultez les champs affichés dans le formulaire avant de valider.', content_width),
            Spacer(1, 3 * mm),
            signal_box('success', 'Comment reconnaître la réussite', module.get('result') or 'Vérifiez que le résultat apparaît dans la liste ou la fiche concernée.', content_width),
            Spacer(1, 3 * mm),
            signal_box('error', 'En cas de blocage ou d’erreur', f"{error_guidance(module)}{' Règle d’accès : ' + module['limits'] if module.get('limits') else ''}", content_width),
            Spacer(1, 3 * mm),
        ])
        for index, instruction in enumerate(module['steps'], start=1):
            story.extend([
                Paragraph(f'Action {index} - {action_title(instruction)}', styles['ManualH2']),
                signal_box('action', f'Étape {index}', instruction, content_width),
                Spacer(1, 2 * mm),
            ])

    story.extend([
        PageBreak(), Paragraph('Dépannage général', styles['ManualH1']),
        Paragraph('Le conseil lié à chaque écran est présenté dans sa rubrique. Les situations générales suivantes complètent ces indications.', styles['ManualBody']),
    ])
    troubleshooting = [
        ('Le menu ou le bouton attendu est absent', 'Vérifiez l’espace et le rôle actifs. Pour l’ADMIN_VILLE, confirmez que la ville est attribuée au compte. Certaines actions n’apparaissent qu’après délégation d’une autorisation.'),
        ('La page affiche une erreur ou ne charge pas', 'Actualisez une fois. Si le problème persiste, notez le nom de l’écran, l’heure, le message affiché et l’action précédente. N’incluez jamais de mot de passe, de jeton ou de donnée personnelle dans la demande de support.'),
        ('Une saisie est refusée', 'Relisez les champs obligatoires, les dates, la devise et les relations avec la ville, le bien ou le contrat. Corrigez uniquement les champs signalés par le message de validation.'),
        ('Vous ne voyez pas les données attendues', 'Vérifiez les filtres, la pagination et le périmètre actif. Les résultats sont bornés à l’organisation et, selon le rôle, aux villes attribuées.'),
    ]
    for title, text in troubleshooting:
        story.extend([Paragraph(title, styles['ManualH2']), Paragraph(text, styles['ManualBody'])])
    story.extend([
        Paragraph('Glossaire', styles['ManualH1']),
        Paragraph('<b>Organisation :</b> espace de gestion qui sépare les données. <b>Ville attribuée :</b> ville autorisée au compte ADMIN_VILLE. <b>Échéance :</b> loyer attendu pour une période. <b>Intervention :</b> tâche de maintenance avec un statut et un bien associé. <b>Devise d’origine :</b> devise dans laquelle un mouvement a été enregistré.', styles['ManualBody']),
        Paragraph('Historique du document', styles['ManualH1']),
        Paragraph(f"Version {DATA['version']} - {DATA['updatedAt']}. Le catalogue partagé alimente le manuel web et ce document. Après toute modification des procédures, régénérez les quatre PDF et vérifiez le rendu.", styles['ManualBody']),
    ])
    doc.multiBuild(story)
    print(f'{output} ({output.stat().st_size} octets)')
    return output


def render_reader_pages(role_key: str, pdf_path: Path, page_count: int) -> None:
    """Crée les vignettes du lecteur à partir des pages PDF, jamais de l’interface."""
    pages_root = OUT / 'manual-pages'
    role_pages = pages_root / role_key
    role_pages.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='soft-immo-pages-') as temporary:
        prefix = Path(temporary) / 'page'
        subprocess.run([
            'pdftoppm', '-jpeg', '-r', '150', '-scale-to', '1800', '-jpegopt', 'quality=88',
            str(pdf_path), str(prefix),
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        rendered = sorted(Path(temporary).glob('page-*.jpg'))
        if len(rendered) != page_count:
            raise SystemExit(f'Rendu incomplet pour {role_key}: {len(rendered)}/{page_count} pages')
        for index, source in enumerate(rendered, start=1):
            shutil.copyfile(source, role_pages / f'page-{index:03d}.jpg')
        for stale in role_pages.glob('page-*.jpg'):
            if int(stale.stem.removeprefix('page-')) > page_count:
                stale.unlink()
    print(f'Pages PDF destinées au lecteur web {role_key}: {page_count}')


if __name__ == '__main__':
    OUT.mkdir(parents=True, exist_ok=True)
    for role_key, role in DATA['roles'].items():
        pdf_path = build(role_key, role)
        page_count = len(PdfReader(str(pdf_path)).pages)
        if page_count < len(role['modules']) + 3:
            raise SystemExit(f'Le PDF {role_key} semble incomplet: {page_count} pages')
        role['pageCount'] = page_count
        render_reader_pages(role_key, pdf_path, page_count)
    CATALOG.write_text(json.dumps(DATA, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
