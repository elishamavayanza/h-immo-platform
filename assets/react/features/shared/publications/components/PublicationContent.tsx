import type { Publication } from '../types/publication.types';

export function PublicationContent({ publication }: { publication: Publication }) {
    const blocks = [];
    for (let index = 0; index < publication.contentBlocks.length;) {
        const block = publication.contentBlocks[index];
        if (block.type === 'bullet' || block.type === 'ordered') {
            const type = block.type;
            const items = [];
            while (index < publication.contentBlocks.length && publication.contentBlocks[index].type === type) {
                items.push(<li key={index}>{publication.contentBlocks[index].text}</li>);
                index++;
            }
            blocks.push(type === 'bullet' ? <ul key={`list-${index}`}>{items}</ul> : <ol key={`list-${index}`}>{items}</ol>);
        } else {
            blocks.push(<p key={index}>{block.text}</p>);
            index++;
        }
    }
    return <div className="publication-content">
        {blocks}
        {publication.images.map((image) => <figure key={image.path} className="publication-content__image">
            <img src={image.url} alt={image.caption || publication.title} loading="lazy" />
            {image.caption && <figcaption>{image.caption}</figcaption>}
        </figure>)}
    </div>;
}
