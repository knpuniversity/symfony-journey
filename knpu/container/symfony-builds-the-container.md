# How Symfony Builds the Container

We rock at building containers. So now let's see how it's build inside of
Symfony.

## Setting up app_dev.php for Debugging

To figure things out, let's jump straight to the code, starting with the
`app_dev.php` front controller. We're going to add some `var_dump` statements
to core classes, and for that to actually work, we need to make a few changes
here. First, instead of loading `bootstrap.php.cache`, require `autoload.php`.
Second, make sure this `$kernel->loadClassCache()` line is commented out:

[[[ code('39d7a26a14') ]]]

A copy of some *really* core classes in Symfony are stored in the cache directory
for a little performance boost. These two changes turn that off so that if
we `var_dump` somewhere, it'll definitely work.

## Booting the Kernel

In the first journey episode, we followed this `$kernel->handle()` method
to find out what happens between the request and response. But this method
does something *else* too. Click to open it up: it lives in a core `Kernel`
class. Inside `handle()`, it calls `boot()` on itself:

[[[ code('5ab87b9541') ]]]

But first, let me back up a second. Remember that the `$kernel` here is an
instance of *our* `AppKernel`, and that extends this core `Kernel`.

The `boot()` method has one job: build the container. And most of the real
work happens inside the `initializeContainer()` function:

[[[ code('fb0bfa4190') ]]]

Hey, this looks really familiar. The container is built on line 558, and
we'll look more at that function. Then its compiled and `dumpContainer()`
writes the cached PHP container class. I'll show you - jump into the
`dumpContainer()` function:

[[[ code('236c59c402') ]]]

Hey, there's our `PhpDumper` class - it does the same thing we did by hand
before.

Back in `initializeContainer()`, it finishes off by requiring the cached
container file and creating a new instance:

[[[ code('ef691775b9') ]]]

So Symfony creates and dumps the container just like we did.

## kernel. and Environment Parameters

There are a lot of little steps that go into building the container, so I'll
jump us to the important parts. Go into `buildContainer()` and look at the
line that calls `$this->getContainerBuilder()`:

[[[ code('67a3843088') ]]]

If we jump to that function, we can see the line that actually creates
the new `ContainerBuilder` object - just like we did before:

[[[ code('c34ee394c4') ]]]

The only addition is that it passes it some parameters to start out. These
are in `getKernelParameters()`:

[[[ code('0cc001645a') ]]]

You probably recognize some of these - like `kernel.root_dir`, and now you
know where they come from. It also calls `getEnvParameters()`:

[[[ code('6623b29e55') ]]]

You may not know about this feature: if you set an environment variable
that starts with `SYMFONY__`, that prefix is stripped and its added as a
parameter automatically. That magic comes from right here

## The Cached Container

Back in `buildContainer()`, let's `var_dump()` the `$container` so far to
see what we've got:

[[[ code('e2bb387744') ]]]

Ok, refresh! Hmm, it didn't hit my code. Why? Well, the container might already
be cached, so it's not going through the building process. To force a build,
you can delete the cached container file. But before you do that, I'll look
inside - it's located at `app/cache/dev/appDevDebugProjectContainer.php`:

[[[ code('9a435a0119') ]]]

It's a lot bigger and has a different class name, but this is just like our
cached container: it has all the parameters on top, then a bunch of methods
to create the services. Now go delete that file and refresh.

```bash
rm app/cache/dev/appDevDebugProjectContainer.php
```

Great: *now* we see the dumped container. I want you to notice a few things.
First, there are *no* service definitions at all. But we do have the 9 parameters.
And that's it - the container is basically empty so far.

## Loading the Yaml Files

To fill it with services, we'll load a Yaml file that'll supply some service
definitions. Back in `buildContainer()`, this
happens when the `registerContainerConfiguration()` method is called:

[[[ code('a0ad70cd6c') ]]]

I did skip a few things - but no worries, we'll cover them in a minute. This
function actually lives in our `AppKernel`:

[[[ code('6e93bf7162') ]]]

The `LoaderInterface` argument is an object that's a lot like the `YamlFileLoader`
that we created manually in `roar.php`. This loader can *also* read other
formats, like XML. But beyond that, it's the same: you create a loader and
then pass it a file full of services.

When Symfony boots, it only loads *one* configuration file - `config_dev.yml`
if you're in the `dev` environment:

[[[ code('ac97eb2aff') ]]]

I know you've looked at that file before, but two really important things
are hiding here. I mentioned earlier that these configuration files have
only three valid root keys: `services` (of course), `parameters` (of course)
and `imports` - to load other files. But in this file - and almost every
file in this directory - you see mostly other stuff, like `framework`, `webprofiler`
and `monolog`. Having these root keys *should* be illegal. But in fact, they're
the secret to how almost every service is added to the container. We'll explore
those next -  so ignore them for now.

The other important thing is that `config_dev.yml` imports `config.yml`:

[[[ code('0af8e8fc54') ]]]

And `config.yml` loads `parameters.yml`, `security.yml` and `services.yml`.
Every file in the `app/config` directory - except the routing files - are
being loaded by the container in order to provide services. In other words,
all of these files have the exact same purpose as the `services.yml` file
we played with before inside of `dino_container`.

The weird part is that none of these files have any services in them, except
for one: `services.yml`:

[[[ code('0f7be5f51a') ]]]

It holds our `user_agent_subscriber` service from episode 1. This gives us
one service definition and `parameters.yml` adds a few parameters.

So after the `registerContainerConfiguration()` line is done, we've gone
from zero services to only 1. Let's dump to prove it - `$container->getDefinitions()`.

[[[ code('9dafdbdfdb') ]]]

Refresh! Yep, there's just our *one* `user_agent_subscriber` service. We can
dump the parameters too - `$container->getParameterBag()->all()`:

[[[ code('3b619451a5') ]]]

This dumps out the `kernel` parameters from earlier plus the stuff from
`parameters.yml`.

So even though the container is still almost empty, we've nearly reached
the end. This empty-ish container is returned to `initializeContainer()`
where it's compiled and then dumped:

[[[ code('247659acc8') ]]]

Before compiling, we only have 1 service. But we know from running `container:debug`
that there are a *lot* of services when things finish. The secret is in the
`compile()` function, which does two special things: process dependency injection
extensions and run compiler passes. Those are up next.
