# Compiler Passes

By the time we got to this step in the `Kernel`, our configuration files
have been loaded, but this gives us just *one* service definition:

[[[ code('fb06e0a394') ]]]

So every other service must be added inside `compile()`. And that's true!

Calling `compile()` executes a group of functions called compiler passes.
In fact, there's one called `MergeExtensionConfigurationPass`, and it's responsible
for the `Extension` system we just looked at:

[[[ code('2de22fedef') ]]]

It loops over the extension objects and calls `load()` on each one. This
is where most of the services come from.

But there's a bunch of other compiler passes, and most do small things. They're
usually registered inside your bundle class - `FrameworkBundle` is a great
example:

[[[ code('f42290e832') ]]]

The `build()` method of every bundle is called early, and is used almost
entirely just to add compiler passes. So what's the point of compiler passes?
Why not just do any container modifications in the extension class?

The special thing about a compiler pass is that when it's called, the entire
container has been built. So it's perfect when you need to tweak the container,
but only once *all* of the service definitions are loaded. 

## Compiler Pass and Tags

Let's see an example. In our `services.yml`, we have one service, and it's
an event subscriber. To tell Symfony this is an event subscriber, we had to
add the tag: `kernel.event_subscriber`:

[[[ code('030e20a24e') ]]]

So how does that work?

It's a compiler pass! And you can see it registered in `FrameworkBundle`,
it's `RegisterListenerPass`:

[[[ code('a10f3ac9f2') ]]]

The `subscriberTag` property is: `kernel.event_subscriber`. Near the bottom,
it calls `$container->findTaggedServiceIds()` and passes it that:

[[[ code('81c22e2249') ]]]

It's saying: give me *all* services tagged with `kernel.event_subscriber`.
The `$definition` variable at the bottom is the Definition object for the
`event_dispatcher`. And we use it to add a method call for `addSubscriberService`
and pass it the service id and the class.

Let's go see this in the cached container. Refresh to get it back, then search
for `user_agent_subscriber`:

[[[ code('f8e9ad8c6a') ]]]

There it is! It's calling the `addSubscriberService` method and passing the
service id and class.

This is one of the most common jobs for a compiler pass. For example, there's
another tag called `form.type` and this `FormPass` looks for all services
tagged with that and does some container tweaking.

And there's a bunch more: like the compiler pass that checks for circular
references. If service A depends on service B, which depends on service C,
which depends on service A, you'll get a really clear exception. Then there
are other passes which make micro-optimizations to speed the container up
even more. 

## Creating a Compiler Pass

Most of the time, you won't need to create a compiler pass - you just need
to understand how they work. But, we're diving deep, so let's make one! In
AppBundle create a new `DependencyInjection` directory and inside of there
a `Compiler` directory. I don't have to put it here, but this follows the
core standard.

In here, create a new class called `EarlyLoggingMessagePass`. Remember how
we logged a message as soon as the logger was created? We're going to do
that again.

Compiler classes are pretty easy - just implement `CompilerPassInterface`
and add the one method: `process()`:

[[[ code('5239e11563') ]]]

Now we should feel really comfortable: that's a `ContainerBuilder` object
and we know all about him. It also has every service already defined inside.
So we can say: `$definition = $container->findDefinition('logger')`. Now
just add `$definition->addMethodCall()` and pass it `debug` for the method,
and an array with a single argument: `Logger CREATED`:

[[[ code('9fdd4c2090') ]]]

And that's a functional compiler pass.

You can register this by overriding the `build()` method in `AppBundle` and
adding it there. But that's too easy. 

Instead, go to `AppKernel` and override `buildContainer()`. Call the parent
method, then add `$container->addCompilerPass()` and pass it a new `EarlyLoggingMessagePass`.
And don't forget to return the `$container`:

[[[ code('79f208e2de') ]]]

Ok, let's try it! Refresh! Click into the profiler then go to the logs tab.
Under debug, there's the message! First on the list. 

Phew! So you're now a master. The Container is all about Definition objects,
which are populated from Yaml and XML files and then updated later in the
dependency injection extension classes. If you're following this, go dive
into the `FrameworkBundle` and see where the *real* core services come from
And congrats, because now, you're a dependency-injection-asaurus!

Ok guys, seeya next time!
