<?php


namespace Lifo\AutocompleteBundle\Form\Type;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Lifo\AutocompleteBundle\Form\DataTransformer\Select2ChoicesTransformer;
use Lifo\AutocompleteBundle\Form\DataTransformer\Select2ValuesTransformer;
use Lifo\AutocompleteBundle\Form\DataTransformer\Select2ValueTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class Select2Type extends AbstractType
{
    private RouterInterface $router;
    private ManagerRegistry $registry;

    public function __construct(RouterInterface $router, ManagerRegistry $registry)
    {
        $this->router = $router;
        $this->registry = $registry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['transformer']) {
            $transformer = is_callable($options['transformer']) ? $options['transformer']($options) : $options['transformer'];
        } else {
            $em = $options['em'] ?? ($options['class'] ? $this->registry->getManagerForClass($options['class']) : null);
            $cls = $options['multiple'] ? Select2ValuesTransformer::class : Select2ValueTransformer::class;
            $tags = !$options['tags'] ? null : [
                'tag_prefix'    => $options['tag_prefix'],
                'tag_id_prefix' => $options['tag_id_prefix'],
            ];
            $transformer = new $cls($em, $options['class'], $options['property'], $options['text_property'], $tags, $options['choices']);
        }
        $builder->addViewTransformer($transformer);
        if ($options['choices']) {
            $builder->addModelTransformer(new Select2ChoicesTransformer($options['choices']));
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (!isset($view->vars['attr']['class'])) {
            $view->vars['attr']['class'] = '';
        }
        $view->vars['attr']['class'] = 'lifo-select2 ' . $view->vars['attr']['class'];
        if ($options['theme'] === 'bootstrap') {
            $view->vars['attr']['class'] .= ' form-control';
        }

        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
        }

        $view->vars['multiple'] = $options['multiple'];
        $view->vars['property'] = $options['property'];
        $view->vars['text_property'] = is_callable($options['text_property'])
            ? $options['text_property']($view->vars['data'])
            : $options['text_property'];

        $view->vars['choices'] = $options['choices'];
        $view->vars['config'] = $this->buildConfig($options);
    }

    /**
     * Returns the config used to initialize the JavaScript Select2 widget.
     *
     * @param array $options
     *
     * @return array
     */
    protected function buildConfig(array $options): array
    {
        $cfg = [
            'multiple'               => $options['multiple'],
            'theme'                  => $options['theme'],
            'placeholder'            => $options['placeholder'],
            'allowClear'             => $options['allow_clear'],
            'minimumInputLength'     => $options['min_input_length'],
            'maximumSelectionLength' => $options['max_selections'],
            'closeOnSelect'          => $options['close_on_select'],
            'dropdownAutoWidth'      => $options['dropdown_auto_width'],
            'dropdownParent'         => $options['dropdown_parent'],
            'tags'                   => $options['tags'],
            'language'               => $options['language'],
            'disabled'               => $options['disabled'],
            'width'                  => $options['width'],
            'dir'                    => $options['dir'],
            'debug'                  => $options['debug'],

            'text_property'    => $options['text_property'],
            'tag_id_prefix'    => $options['tag_id_prefix'],
            'tag_prefix'       => $options['tag_prefix'],
            'submit_on_select' => $options['submit_on_select'],
        ];

        // define source of data: AJAX from a local route, a third-party URL, or an array of choices
        if ($options['choices']) {
            $cfg['data'] = $options['choices'];
        } else {
            if ($options['route']) {
                $ajax['url'] = $this->router->generate($options['route'], $options['route_params'] ?? []);
            } elseif ($options['url']) {
                $ajax['url'] = $options['url'];
            } else {
                throw new InvalidOptionsException('Missing option "route" or "url" in ' . self::class);
            }
            $ajax['delay'] = $options['delay'];
            $ajax['cache'] = $options['cache'];
            $ajax['cacheTimeout'] = $options['cache_timeout'];
            $cfg['ajax'] = $ajax;
        }
        return $cfg;
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $emNormalizer = function (Options $options, $value) {
            return is_callable($value) ? $value($options) : $value;
        };

        $choicesNormalizer = function (Options $options, $value) {
            $value = is_callable($value) ? $value($options) : $value;
            // convert assoc array into indexed
            if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
                return array_map(fn($k) => ['id' => $value[$k], 'text' => $k], array_keys($value));
            }
            return $value;
        };

        $resolver->setDefaults([
            'em'                  => null,
            'class'               => null,
            'property'            => 'id',
            'text_property'       => null,
            'multiple'            => false,
            'min_input_length'    => 0,
            'max_selections'      => 0,
            'delay'               => 250,
            'width'               => '100%',
            'choices'             => null,
            'url'                 => null,
            'route'               => null,
            'route_params'        => null,
            'tags'                => false,
            'tag_id_prefix'       => '__NEWTAG__:',
            'tag_prefix'          => '',
            'cache'               => true,
            'cache_timeout'       => 60,
            'theme'               => 'bootstrap', // todo change to 'default'
            'language'            => null,
            'disabled'            => false,
            'dir'                 => 'ltr',
            'dropdown_parent'     => null,
            'allow_clear'         => true,
            'close_on_select'     => true,
            'submit_on_select'    => false,
            'dropdown_auto_width' => false,
            'placeholder'         => null,
            'transformer'         => null,
            'debug'               => false,
            'compound'            => false,
        ]);
        $resolver->setNormalizer('em', $emNormalizer);
        $resolver->setNormalizer('placeholder', fn(Options $options, $value) => $value ?? ''); // no null
        $resolver->setNormalizer('choices', $choicesNormalizer);
        $resolver->addAllowedValues('dir', ['ltr', 'rtl']);
        $resolver->addAllowedTypes('transformer', ['null', 'callable', DataTransformerInterface::class]);
        $resolver->addAllowedTypes('choices', ['null', 'iterable', 'callable']);
        $resolver->addAllowedTypes('em', ['null', 'callable', EntityManagerInterface::class]);
        $resolver->addAllowedTypes('property', ['string']);
        $resolver->addAllowedTypes('text_property', ['null', 'string', 'callable']);
    }

    public function getBlockPrefix()
    {
        return 'lifo_select2';
    }
}