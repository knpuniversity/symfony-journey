# Dependency Injection Extensions

These Yaml files *should* only have keys for `services`, `parameters` and
`imports`. What if I just make something up, like `journey` and put a
`dino_count` of 10 under it:

[[[ code('') ]]]

When we refresh, we get a *huge* error!

    There is no extension able to load the configuration for "journey".

And it says it found valid namespaces for `framework`, `security`, `twig`,
`monolog`, blah blah blah. Hey, *those* are the root keys that we have in
our config files. So what makes `journey` invalid but `framework` valid?
And what does `framework` do anyways?

Take out that `journey` code.

## Registering of Extension Classes

The answer can be found in your bundle classes. Open up `AppBundle`:

[[[ code('6485adf997') ]]]

This is empty, but it extends Symfony's base `Bundle` class. The key method
is `getContainerExtension()`:

[[[ code('') ]]]

When Symfony boots, it calls this method on each bundle looking for something
called an Extension. This calls `getContainerExtensionClass()` and checks
to see if that class exists. Move down to that method:

[[[ code('') ]]]

Ah, and *here's* the magic. To find this "extension" class, it looks for
a `DependencyInjection` directory and a class with the same name as bundle,
except replacing `Bundle` with `Extension`. For example, for AppBundle, it's
looking for a `DependencyInjection\AppExtension` class inside. We don't have
that.

Open up the TwigBundle class and double-click the directory tree at the top
to move into that directory. TwigBundle *does* have a `DependencyInjection`
directory and a `TwigExtension` inside. So because this is there, it's automatically
registered with the container. We may not know what an extension does yet,
but we know how it's all setup.

## Registering Twig Globals

Forget about extensions for one second and let me tell you about a totally
unrelated feature. If you want to add a global variable to Twig, one way
to do that is under the `twig` config. Just add `globals`, then set something
up. I'll say `twitter_username: weaverryan`:

[[[ code('') ]]]

And just by doing that, we could open up any Twig template and have access
to a `twitter_username` variable. My question is: how does that work?

## The Extension load() Method

To answer that, look back at `TwigExtension`. The first secret is that when
we call `compile()` on the container, this `load()` method is called. In
fact the `load()` method is called on *every* extension that's registered
with Symfony: so every class that follows the DependencyExtension/Extension
naming-convention.

Let's dump the `$configs` variable, because I don't know what that is yet:

[[[ code('') ]]]

Go back and refresh! Woh! It dumps an array with the Twig configuration!
Whatever we have in `config.yml` under `twig` is getting passed to `TwigExtension`:

[[[ code('') ]]]

In fact, *that's* the rule. The fact that we have a key called `framework`
means that this will all be passed to a class called `FrameworkExtension`.
If you want to see how this config is used, look there. With the `assetic`
key, that's passed to `AsseticExtension`. These extension classes have a
`getAlias()` function on them, which default to a lower-cased version of
the class name without the word `Extension`.

## Extensions Load Services

These extensions have two jobs. First, they add service definitions to the
container. And afterall, the main reason for bringing in a bundle is to add
services to your container. 

The way it does this is just like our `roar.php` file, except it loads an
XML file instead of Yaml. Let's open up that `Resources/config/twig.xml`
file:

[[[ code('') ]]]

If you ever wondered where the `twig` service comes from, it's right here!
So the first job of an extension class is to add services, which it always
does by loading one or more XML files.

## Extensions Configuration

The second job is to read our configuration array and use that information
to mutate the service definitions. We'll see code that does this shortly.

Most extensions will have two lines near the top that call `getConfiguration()`
and `processConfiguration`. Next to every extension class, you'll find a
class called `Configuration`:

[[[ code('') ]]]

One cool feature about all of this configuration is that if you make a type
in a file like `config.yml` - say you spell `globals` as `globalsss`, you'll
get a really good error. All of this configuration is validated.

These `Configuration` classes make that happen. This is probably one of the
more bizarre classes you'll see: it builds a tree of valid configuration
that can be used under this key. It adds a `globals` section, which says
that the children are an array. It even has some stuff to validate and normalize
what we put here. These `Configuration` classes are tough to write, but pretty
easy to read. And if you can't get something to configure correctly, opening
up the right `Configuration` class might give you a hint.

Back in `TwigExtension`, let's dump `$config` after calling `processConfiguration()`:

[[[ code('') ]]]

This dumps out a nice, normalized and validated version of our config, including
keys we didn't have, with their default values. 

## Extensions Mutate Definitions

So finally, how is the `globals` key used? Scroll down to around line 90:

[[[ code('') ]]]

For most people, this code will look weird. But not us! If there are globals,
it gets the `twig` Definition back *out* of the `ContainerBuilder`. This
definition was added when it loaded `twig.xml`, and now we're going to change
it based on some config. Just focus on the second part of the `if`: it calls
`$def->addMethodCall()` and passes it `addGlobal` and two arguments: our
key from the config, and the value - `weaveerryan` in this case.

If you read the Twig documentation, it tells you that if you want to add
a global variable, you can call `addGlobal` on the `Twig_Environment` object.
And that's exactly what this does. And this is basically what *all* extension
classes are doing. 

If you refresh without any debug code, we'll get a working page again. Now
open up the cached container - `app/cache/dev/appDevDebugProjectContainer.php`
and find the method that creates the `twig` service - `getTwigService()`.
Make sure you spell that correctly:

[[[ code('') ]]]

Near the bottom, we see it: `$instance->addGlobal('twitter_username', 'weaverryan')`.
We passed in simple configuration, `TwigExtension` used that to mutuate the
`twig` Definition, and ultimately the dumped container is updated. 

That's the power of the dependency injection extensions, and if it makes
even a bit of sense, you're dangerous.

## Our Configuration Wins

Oh, and one more cool note. These extension classes are called when the container
is compiling, which means it happens *after* our config files are loaded.

But the container is setup so that any parameters or services that you put
inside of your configuration files will override those inside the bundle.
You want to be careful when you override core stuff, but technically, it's
easy.
