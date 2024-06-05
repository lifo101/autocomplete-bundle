<?php


namespace Lifo\AutocompleteBundle\Form\Type;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Lifo\AutocompleteBundle\Form\DataTransformer\MultiselectValuesTransformer;
use Lifo\AutocompleteBundle\Form\DataTransformer\MultiselectValueTransformer;
use Lifo\AutocompleteBundle\Form\DataTransformer\Select2ChoicesTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MultiselectType extends AbstractType
{
    private RouterInterface     $router;
    private ManagerRegistry     $registry;
    private SerializerInterface $serializer;

    public function __construct(RouterInterface $router, ManagerRegistry $registry, SerializerInterface $serializer)
    {
        $this->router = $router;
        $this->registry = $registry;
        $this->serializer = $serializer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['transformer']) {
            $transformer = is_callable($options['transformer']) ? $options['transformer']($options) : $options['transformer'];
        } else {
            $em = $options['em'] ?? ($options['class'] ? $this->registry->getManagerForClass($options['class']) : null);
            $cls = $options['multiple'] ? MultiselectValuesTransformer::class : MultiselectValueTransformer::class;
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
        $view->vars['config'] = $this->buildConfig($options, $view);
        // for mock-up skeleton only
        $view->vars['size'] = $options['size'];
        $view->vars['skeleton'] = $options['skeleton'];
        $view->vars['vueVersion'] = $options['vueVersion'];

        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
        }

        $view->vars['multiple'] = $options['multiple'];
        $view->vars['property'] = $options['property'];
        $view->vars['text_property'] = is_callable($options['text_property'])
            ? $options['text_property']($view->vars['data'])
            : $options['text_property'];

        $view->vars['choices'] = $options['choices'];
    }

    /**
     * Returns the config used to initialize the MultiselectAjax widget.
     *
     * @param array    $options
     * @param FormView $form
     *
     * @return array
     */
    protected function buildConfig(array $options, FormView $form): array
    {
        // the 'var' and ':var' pairs are needed for the searchBuilder since it doesn't render the ':var' properly
        $cfg = array_merge($options['attr'] ?? [], [
            'class'                => $options['vueVersion'] < 3 ? 'lifo-vue-multiselect' : null,
            'is'                   => $options['vueVersion'] < 3 ? 'multiselect-ajax' : null,
            ':vue-version'         => $options['vueVersion'],
            'minimum-input-length' => $options['min_input_length'],
            'required'             => $options['required'] ? '' : null,
            ':required'            => $options['required'] ? 'true' : 'false',
            'multiple'             => $options['multiple'] ? '' : null,
            ':multiple'            => $options['multiple'] ? 'true' : 'false',
            'size'                 => $options['size'],
            'label'                => $options['text_property'] ?? 'label',
            'placeholder'          => $options['placeholder'] ?? null,
            'track-by'             => $options['property'] ?? 'id',
            'search-param'         => $options['term_param'] ?? 'search',
            'show-labels'          => $options['show_labels'] ?? false ? '' : null,
            ':show-labels'         => $options['show_labels'] ?? true ? 'true' : 'false',
            'preserve-search'      => $options['preserve_search'] ?? true ? '' : null,
            ':preserve-search'     => $options['preserve_search'] ?? true ? 'true' : 'false',
            'internal-search'      => $options['internal_search'] ?? false ? '' : null,
            ':internal-search'     => $options['internal_search'] ?? false ? 'true' : 'false',
            'url'                  => $options['route'] ? $this->router->generate($options['route'], $options['route_params'] ?? []) : $options['url'] ?? null,
            $options['vueVersion'] < 3 ? ':value' : ':model-value'
                                   => $form->vars['data'] === null ? 'null' : $this->serializer->serialize($form->vars['data'], $options['serializerFormat'], $options['serializerContext'] ?? []),
        ]);
        return array_filter($cfg, fn($v) => $v !== null);
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
            'size'                => null,
            'skeleton'            => false,
            'multiple'            => false,
            'min_input_length'    => 0,
            'max_selections'      => 0,
            'delay'               => 250,
            'width'               => '100%',
            'choices'             => null,
            'url'                 => null,
            'term_param'          => null, // override '?term=' param name for ajax calls
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
            'show_labels'         => true,
            'close_on_select'     => true,
            'submit_on_select'    => false,
            'dropdown_auto_width' => false,
            'placeholder'         => null,
            'transformer'         => null,
            'debug'               => false,
            'compound'            => false,
            'serializerFormat'    => 'json',
            'serializerContext'   => [],
            'vueVersion'          => 2,
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
        $resolver->addAllowedTypes('serializerFormat', ['string']);
        $resolver->addAllowedTypes('serializerContext', ['null', 'array']);
        $resolver->addAllowedTypes('vueVersion', ['string', 'numeric']);
    }

    public function getBlockPrefix(): string
    {
        return 'lifo_multiselect';
    }
}