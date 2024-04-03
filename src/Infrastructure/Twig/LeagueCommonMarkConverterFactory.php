<?php

namespace App\Infrastructure\Twig;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\NodeRendererInterface;

class LeagueCommonMarkConverterFactory
{
    private $extensions;

    /**
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(iterable $extensions)
    {
        $this->extensions = $extensions;
    }

    public function __invoke(): CommonMarkConverter
    {
        $config = [
//            'renderer' => [
//                'allow_unsafe_links' => false,
//            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => [
                        'class' => 'table-responsive',
                    ],
                ],
            ],
        ];

        $converter = new CommonMarkConverter($config);

        foreach ($this->extensions as $extension) {
            $converter->getEnvironment()->addExtension($extension);
        }

        $converter->getEnvironment()->addRenderer(
            Table::class,
            new class(new TableRenderer()) implements NodeRendererInterface {
                public function __construct(private TableRenderer $tableRenderer)
                {
                }
                public function render(Node $node, $childRenderer)
                {
                    $node->data->set('attributes', ['class' => 'table table-bordered']);
                    return $this->tableRenderer->render($node, $childRenderer);
                }
            }
        );
        $converter->getEnvironment()->addRenderer(
            FencedCode::class,
            new class implements NodeRendererInterface {
                public function render(Node $node, $childRenderer)
                {
                    return '<pre><code>' . htmlspecialchars($node->getLiteral()) . '</code></pre>';
                }
            }
        );

        return $converter;
    }
}