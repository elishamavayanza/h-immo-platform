#!/usr/bin/env python3
"""Génère les PDF de guide depuis le catalogue commun versionné."""
from __future__ import annotations
import json
import shutil
import subprocess
import tempfile
from pathlib import Path
from PIL import Image as PILImage
from pypdf import PdfReader
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, PageBreak,
                                Image, Table, TableStyle, KeepTogether, ListFlowable, ListItem)
from reportlab.platypus.tableofcontents import TableOfContents

ROOT=Path(__file__).resolve().parents[1]
CATALOG=ROOT/'docs/user-manuals/catalog.json'
SCREENSHOTS=ROOT/'output/pdf/screenshots'
OUT=ROOT/'output/pdf'
LOGO=ROOT/'public/logo.png'
DATA=json.loads(CATALOG.read_text(encoding='utf-8'))

# Les captures stockées dans ce dossier sont déjà anonymisées (profil et
# lignes contenant des données personnelles masqués). On ne les réécrit pas
# pour que des générations successives ne dégradent pas les images.
used={m['image'] for role in DATA['roles'].values() for m in role['modules']}
for filename in sorted(used):
    path=SCREENSHOTS/filename
    if not path.is_file():
        raise SystemExit(f"Capture manquante: {path}")
    with PILImage.open(path) as image:
        if image.format not in {'JPEG', 'PNG'}:
            raise SystemExit(f"Format de capture non pris en charge: {path}")
# Écarte de l’artefact livré les captures de répertoires d’utilisateurs/audit non utilisées.
for p in SCREENSHOTS.iterdir():
    if p.is_file() and p.name not in used:
        p.unlink()

font='/usr/share/fonts/truetype/msttcorefonts/Times_New_Roman.ttf'
font_b='/usr/share/fonts/truetype/msttcorefonts/Times_New_Roman_Bold.ttf'
font_i='/usr/share/fonts/truetype/msttcorefonts/Times_New_Roman_Italic.ttf'
font_bi='/usr/share/fonts/truetype/msttcorefonts/Times_New_Roman_Bold_Italic.ttf'
pdfmetrics.registerFont(TTFont('TimesNewRoman',font)); pdfmetrics.registerFont(TTFont('TimesNewRoman-Bold',font_b))
pdfmetrics.registerFont(TTFont('TimesNewRoman-Italic',font_i)); pdfmetrics.registerFont(TTFont('TimesNewRoman-BoldItalic',font_bi))
pdfmetrics.registerFontFamily('TimesNewRoman',normal='TimesNewRoman',bold='TimesNewRoman-Bold',italic='TimesNewRoman-Italic',boldItalic='TimesNewRoman-BoldItalic')
ink=colors.HexColor('#263640'); teal=colors.HexColor('#287889'); muted=colors.HexColor('#647780'); pale=colors.HexColor('#edf2f1'); line=colors.HexColor('#d1dcdd'); gold=colors.HexColor('#c18b31')
base=getSampleStyleSheet()
styles={
 'cover_title':ParagraphStyle('cover_title',fontName='TimesNewRoman-Bold',fontSize=27,leading=33,textColor=ink,alignment=TA_CENTER,spaceAfter=12),
 'cover_sub':ParagraphStyle('cover_sub',fontName='TimesNewRoman',fontSize=14,leading=20,textColor=muted,alignment=TA_CENTER),
 'h1':ParagraphStyle('ManualH1',fontName='TimesNewRoman-Bold',fontSize=18,leading=23,textColor=ink,spaceBefore=10,spaceAfter=8,keepWithNext=True),
 'h2':ParagraphStyle('ManualH2',fontName='TimesNewRoman-Bold',fontSize=14,leading=18,textColor=teal,spaceBefore=8,spaceAfter=4,keepWithNext=True),
 'h3':ParagraphStyle('ManualH3',fontName='TimesNewRoman-Bold',fontSize=12,leading=15,textColor=ink,spaceBefore=5,spaceAfter=3,keepWithNext=True),
 'body':ParagraphStyle('ManualBody',fontName='TimesNewRoman',fontSize=11.5,leading=16.5,textColor=ink,spaceAfter=7),
 'small':ParagraphStyle('ManualSmall',fontName='TimesNewRoman',fontSize=9,leading=12,textColor=muted,spaceAfter=4),
 'caption':ParagraphStyle('ManualCaption',fontName='TimesNewRoman-Italic',fontSize=9.5,leading=12,textColor=muted,alignment=TA_CENTER,spaceBefore=5,spaceAfter=9),
 'note':ParagraphStyle('ManualNote',fontName='TimesNewRoman',fontSize=11,leading=15.5,textColor=ink,backColor=pale,borderColor=line,borderWidth=.6,borderPadding=8,spaceBefore=4,spaceAfter=8),
 'toc1':ParagraphStyle('TOC1',fontName='TimesNewRoman',fontSize=12,leading=17,textColor=ink,leftIndent=0,firstLineIndent=0,spaceBefore=4),
 'toc2':ParagraphStyle('TOC2',fontName='TimesNewRoman',fontSize=10.5,leading=14,textColor=muted,leftIndent=14,firstLineIndent=0,spaceBefore=2),
}
class ManualDoc(BaseDocTemplate):
 def __init__(self,path,role):
  super().__init__(str(path),pagesize=A4,leftMargin=21*mm,rightMargin=21*mm,topMargin=22*mm,bottomMargin=19*mm,title=role['title'],author='Soft-IMMO')
  frame=Frame(self.leftMargin,self.bottomMargin,self.width,self.height,id='normal')
  self.addPageTemplates([PageTemplate(id='main',frames=frame,onPage=self.decorate)])
 def decorate(self,canvas,doc):
  if doc.page==1:return
  canvas.saveState(); w,h=A4
  canvas.setStrokeColor(line);canvas.setLineWidth(.5);canvas.line(21*mm,h-15*mm,w-21*mm,h-15*mm)
  canvas.setFont('TimesNewRoman-Bold',9);canvas.setFillColor(teal);canvas.drawString(21*mm,h-12*mm,'SOFT-IMMO  |  GUIDE UTILISATEUR')
  canvas.setStrokeColor(line);canvas.line(21*mm,14*mm,w-21*mm,14*mm)
  canvas.setFont('TimesNewRoman',9);canvas.setFillColor(muted);canvas.drawString(21*mm,9*mm,f"Version {DATA['version']} · {DATA['updatedAt']}")
  canvas.drawRightString(w-21*mm,9*mm,f"Page {doc.page}")
  canvas.restoreState()
 def afterFlowable(self,flowable):
  if isinstance(flowable,Paragraph) and flowable.style.name=='ManualH1' and flowable.getPlainText()!='Table des matières':
   title=flowable.getPlainText(); key=f'h{self.page}-{abs(hash(title))}'
   self.canv.bookmarkPage(key); self.canv.addOutlineEntry(title,key,0,False)
   self.notify('TOCEntry',(0,title,self.page,key))

def footer_logo(width=38*mm):
 im=PILImage.open(LOGO); ratio=im.height/im.width
 return Image(str(LOGO),width=width,height=width*ratio,kind='proportional')
def add_bullets(texts):
 return ListFlowable([ListItem(Paragraph(x,styles['body']),leftIndent=11) for x in texts],bulletType='bullet',start='circle',leftIndent=13,bulletFontName='TimesNewRoman',bulletFontSize=8,spaceAfter=4)
def build(role_key,role):
 out=OUT/role['filename']; doc=ManualDoc(out,role); story=[]
 story += [Spacer(1,18*mm),footer_logo(42*mm),Spacer(1,17*mm),Paragraph('MANUEL D’UTILISATION',styles['cover_sub']),Spacer(1,6*mm),Paragraph(role['title'].replace('Manuel d’utilisation — ',''),styles['cover_title']),Spacer(1,4*mm),Paragraph(role['label'],ParagraphStyle('role',parent=styles['cover_sub'],fontName='TimesNewRoman-Bold',textColor=teal,fontSize=12)),Spacer(1,7*mm),Paragraph(role['description'],styles['cover_sub']),Spacer(1,18*mm)]
 t=Table([[Paragraph(f"Version <b>{DATA['version']}</b>",styles['body']),Paragraph(f"Mise à jour <b>{DATA['updatedAt']}</b>",styles['body'])]],colWidths=[75*mm,75*mm]);t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),pale),('BOX',(0,0),(-1,-1),.6,line),('INNERGRID',(0,0),(-1,-1),.4,line),('VALIGN',(0,0),(-1,-1),'MIDDLE'),('LEFTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),10),('BOTTOMPADDING',(0,0),(-1,-1),10)]));story += [t,Spacer(1,20*mm),Paragraph('Document établi à partir des écrans et des règles d’accès de Soft-IMMO. Les libellés peuvent évoluer avec les mises à jour de l’application.',styles['caption']),PageBreak()]
 story += [Paragraph('Table des matières',styles['h1'])]
 toc=TableOfContents();toc.levelStyles=[styles['toc1'],styles['toc2']];toc.dotsMinLevel=0;story += [toc,PageBreak()]
 story += [Paragraph('Bienvenue dans votre espace',styles['h1']),Paragraph(role['description'],styles['body']),Paragraph('<b>Accès requis.</b> Connectez-vous avec un compte actif. Les menus visibles dépendent de votre rôle et, pour les rôles d’organisation, de l’organisation active et des villes qui vous sont attribuées.',styles['body']),Paragraph('<b>Navigation.</b> Le menu latéral ouvre les modules. Le sélecteur d’espace permet de vérifier le rôle et l’organisation actifs. Les paramètres de compte permettent de changer le thème, la palette et d’autres préférences.',styles['body']),Paragraph('Sécurité et périmètre',styles['h2']),Paragraph('Ne partagez pas votre mot de passe ni vos jetons de connexion. Vérifiez l’organisation ou la ville active avant une saisie. L’API vérifie les autorisations côté serveur ; une action absente ou refusée ne doit pas être contournée en modifiant l’adresse de la page.',styles['body']),PageBreak()]
 for index,mod in enumerate(role['modules'],1):
  story.append(Paragraph(mod['name'],styles['h1']))
  story += [Paragraph(f"<b>Menu :</b> {mod['menu']}",styles['body']),Paragraph(f"<b>Objectif :</b> {mod['goal']}",styles['body']),Paragraph('Procédure',styles['h2']),add_bullets(mod['steps']),Paragraph(f"<b>Champs et vérifications :</b> {mod['fields']}",styles['body']),Paragraph(f"<b>Résultat attendu :</b> {mod['result']}",styles['body']),Paragraph(f"<b>Accès et limites :</b> {mod['limits']}",styles['note'])]
  im=SCREENSHOTS/mod['image']; pic=PILImage.open(im); iw,ih=pic.size; maxw=168*mm; maxh=110*mm; scale=min(maxw/iw,maxh/ih)
  story += [Spacer(1,3*mm),Image(str(im),width=iw*scale,height=ih*scale),Paragraph(f"Capture d’interface — {mod['menu']}. Consultez les libellés visibles ; les droits et les données peuvent varier selon le compte.",styles['caption']),Spacer(1,5*mm)]
 story += [PageBreak(),Paragraph('Dépannage et bonnes pratiques',styles['h1'])]
 troubleshooting=[('Le menu ou le bouton attendu est absent','Vérifiez l’espace, l’organisation et la ville actifs. Certaines actions nécessitent une autorisation déléguée.'),('La page affiche une erreur ou ne charge pas','Actualisez une fois. Si le problème persiste, notez la route, l’heure, l’identifiant de l’erreur et l’action précédente, puis contactez l’administrateur.'),('Une saisie est refusée','Relisez les champs obligatoires, la date, la devise et les relations avec la ville ou le bien. Le message de validation précise généralement le champ à corriger.'),('Vous ne voyez pas les données attendues','Vérifiez les filtres et le périmètre actif. Les listes sont filtrées par organisation et, selon le rôle, par ville.')]
 for title,body in troubleshooting: story += [Paragraph(title,styles['h2']),Paragraph(body,styles['body'])]
 story += [Paragraph('Glossaire',styles['h1']),Paragraph('<b>Organisation :</b> espace de gestion et frontière des données. <b>Ville attribuée :</b> ville autorisée au compte ADMIN_VILLE. <b>Échéance :</b> loyer attendu pour une période. <b>Intervention :</b> tâche de maintenance avec un statut et un bien associé. <b>Devise d’origine :</b> devise dans laquelle un mouvement a été enregistré.',styles['body']),Paragraph('Historique du document',styles['h1']),Paragraph(f"Version {DATA['version']} — {DATA['updatedAt']}. Le contenu et les captures sont centralisés dans le catalogue de manuels du projet ; régénérez les PDF après chaque changement de menu ou d’autorisation.",styles['body'])]
 doc.multiBuild(story)
 print(f"{out} ({out.stat().st_size} octets)")

if __name__=='__main__':
 OUT.mkdir(parents=True,exist_ok=True)
 for key,role in DATA['roles'].items(): build(key,role)
 for role in DATA['roles'].values():
  role['pageCount']=len(PdfReader(str(OUT/role['filename'])).pages)
 pages_root=OUT/'manual-pages'
 if pages_root.exists(): shutil.rmtree(pages_root)
 for key,role in DATA['roles'].items():
  role_pages=pages_root/key; role_pages.mkdir(parents=True,exist_ok=True)
  with tempfile.TemporaryDirectory(prefix='soft-immo-pages-') as temp:
   prefix=Path(temp)/'page'
   subprocess.run(['pdftoppm','-jpeg','-r','150','-scale-to','1800','-jpegopt','quality=88',str(OUT/role['filename']),str(prefix)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
   rendered=sorted(Path(temp).glob('page-*.jpg'))
   if len(rendered)!=role['pageCount']:
    raise SystemExit(f"Rendu incomplet pour {key}: {len(rendered)}/{role['pageCount']} pages")
   for index,source in enumerate(rendered,1): shutil.copyfile(source,role_pages/f'page-{index:03d}.jpg')
  print(f"Pages lecteur {key}: {role['pageCount']}")
 CATALOG.write_text(json.dumps(DATA,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
