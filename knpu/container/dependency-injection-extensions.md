# Dependency Injection Extensions

These Yaml files *should* only have keys for `services`, `parameters` and
`imports`. What if I just make something up, like `journey` and put a
`dino_count` of 10 under it:

[[[ code('de2e78ce46') ]]]

When we refresh, we get a *huge* error!

    There is no extension able to load the configuration for "journey".

And it says it found valid namespaces for `framework`, `security`, `twig`,
`monolog`, blah blah blah. Hey, *those* are the root keys that we have in
our config files. So what makes `journey` invalid but `framework` valid?
And what does `framework` do anyways?

Take out that `journey` code.

## Registering of Extension Classes

The answer lives in the bundle classes. Open up `AppBundle`:

[[[ code('6485adf997') ]]]

This is empty, but it extends Symfony's base `Bundle` class. The key method
is `getContainerExtension()`:

[[[ code('c9c0aecf95') ]]]

When Symfony boots, it calls this method on each bundle looking for something
called an Extension. This calls `getContainerExtensionClass()` and checks
to see if that class exists. Move down to that method:

[[[ code('38584037b0') ]]]

Ah, and *here's* the magic. To find this "extension" class, it looks for
a `DependencyInjection` directory and a class with the same name as the bundle,
except replacing `Bundle` with `Extension`. For example, for AppBundle, it's
looking for a `DependencyInjection\AppExtension` class. We don't have that.

Open up the TwigBundle class and double-click the directory tree at the top
to move PhpStorm here. TwigBundle *does* have a `DependencyInjection`
directory and a `TwigExtension` inside:

[[[ code('0f2b0b5267') ]]]

So because this is here, it's automatically registered with the container.
We may not know what an extension does yet, but we know how it's all setup.

## Registering Twig Globals

Forget about extensions for a second and let me tell you about a totally
unrelated feature. If you want to add a global variable to Twig, one way
to do that is under the `twig` config. Just add `globals`, then set something
up. I'll say `twitter_username: weaverryan`:

[[[ code('e2eca6fa75') ]]]

And just by doing that, we could open up any Twig template and have access
to a `twitter_username` variable. My question is: how does that work?

## The Extension load() Method

To answer that, look back at `TwigExtension`. The first secret is that when
we call `compile()` on the container, this `load()` method is called. In
fact the `load()` method is called on *every* extension that's registered
with Symfony: so every class that follows the DependencyInjection\Extension
naming-convention.

Let's dump the `$configs` variable, because I don't know what that is yet:

[[[ code('70c46e0a7f') ]]]

Go back and refresh! Ok: it dumps an array with the `twig` configuration.
Whatever we have in `config.yml` under `twig` is getting passed to `TwigExtension`:

In fact, *that's* the rule. The fact that we have a key called `framework`
means that this config will be passed to a class called `FrameworkExtension`.
If you want to see how this config is used, look there. With the `assetic`
key, that's passed to `AsseticExtension`. These extension classes have a
`getAlias()` method in them, and that returns a lower-cased version of
the class name without the word `Extension`.

## Extensions Load Services

These extensions have two jobs. First, they add service definitions to the
container. Because after all, the main reason for adding a bundle is to add
services to your container.

The way it does this is just like our `roar.php` file, except it loads an
XML file instead of Yaml:

[[[ code('25f3cb7db0') ]]]

Let's open up that `Resources/config/twig.xml` file:

[[[ code('9f08615ef2') ]]]

If you ever wondered where the `twig` service comes from, it's right here!
You can see it in `container:debug`:

```bash
php app/console container:debug twig
```

So the first job of an extension class is to add services, which it always
does by loading one or more XML files.

## Extensions Configuration

The second job is to read our configuration array and use that information
to mutate the service definitions. We'll see code that does this shortly.

Most extensions will have two lines near the top that call `getConfiguration()`
and `processConfiguration`:

[[[ code('d71114ee28') ]]]

Next to every extension class, you'll find a class called `Configuration`:

[[[ code('486404aa56') ]]]

Watch out, a meteor! Oh, never mind, it's just the awesome fact that if I
mess up some configuration - like `globals` as `globalsss` in Yaml, we'll
get a really nice error. That doesn't happen by accident, that system *evolved*
these `Configuration` classes to make that happen.

This is probably one of the more bizarre classes you'll see: it builds a
tree of valid configuration that can be used under this key. It adds a `globals`
section, which says that the children are an array.It even has some stuff
to validate and normalize what we put here:

[[[ code('424062792d') ]]]

These `Configuration` classes are tough to write, but pretty easy to read.
And if you can't get something to configure correctly, opening up the right
`Configuration` class might give you a hint.

Back in `TwigExtension`, let's dump `$config` after calling `processConfiguration()`:

[[[ code('60d86c6d43') ]]]

This dumps out a nice, normalized and validated version of our config, including
keys we didn't have, with their default values. 

## Extensions Mutate Definitions

So finally, how is the `globals` key used? Scroll down to around line 90:

[[[ code('7938578492') ]]]

For most people, this code will look weird. But not us! If there are globals,
it gets the `twig` Definition back *out* of the `ContainerBuilder`. This
definition was added when it loaded `twig.xml`, and now we're going to tweak
it. Just focus on the second part of the `if`: it calls `$def->addMethodCall()`
and passes it `addGlobal` and two arguments: our key from the config, and
the value - `weaverryan` in this case.

If you read the Twig documentation, it tells you that if you want to add
a global variable, you can call `addGlobal` on the `Twig_Environment` object.
And that's exactly what this does. This type of stuff is *super* typical
for extensions.

If you refresh without any debug code, we'll get a working page again. Now
open up the cached container - `app/cache/dev/appDevDebugProjectContainer.php`
and find the method that creates the `twig` service - `getTwigService()`.
Make sure you spell that correctly:

[[[ code('a951703eb6') ]]]

Near the bottom, we see it: `$instance->addGlobal('twitter_username', 'weaverryan')`.
We passed in simple configuration, `TwigExtension` used that to mutate the
`twig` Definition, and ultimately the dumped container is updated. 

That's the power of the dependency injection extensions, and if it makes
even a bit of sense, you're awesome.

## Our Configuration Wins

Oh, and one more cool note. If I added a `twig` service to `config.yml`,
would it override the one from `TwigBundle`? Actually yes: even though the
extensions are called after loading these files, any parameters or services
we add here win.
