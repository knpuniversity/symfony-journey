# Definitions: Teach the Container

We've got two problems. First, our services are *always* created. What if
we had a `mailer` service? You only need to mail something on a very small
percentage of requests. With this setup, we'll spend time and memory on *every*
request to create the mailer object, even though we don't need it. That's
bananas! Especially if you have a big system.

Second, the services need to be created in order: we *have* to create the
`$streamHandler` first so that it's available when we create the logger.
If we reorder things, it'll blow up. With a big system where services are
created in many places, this will get tricky fast. 

Here's the answer: don't create the services. Instead, let's *teach* the
container *how* to create them. Then *it* will create the objects when and
*if* we ask for them.

This means that instead of creating a `Logger`, create an `$loggerDefinition`
variable and set it to a new `Definition` object. For the first argument,
pass it the class name - `Monolog\Logger`.

[[[ code('5e5830c030') ]]]

This `Definition` object knows everything about *how* to instantiate an object.
We'll use it to *teach* the container that when I ask for the `logger` service,
this is *how* you should create it. So naturally, if the class has constructor
arguments, we need to configure those. Do that with `$loggerDefinition->setArguments()`,
and this takes an array of the arguments. The first is just a string: `main`:

[[[ code('0280b11f45') ]]]

The second argument is an array of handler objects. So you might expect me
to just pass `$container->get('logger.stream_handler')`. But no! That would
mean that I'd *still* have to worry about creating the stream handler first.

Instead, we can *refer* to the service by its id. Create a new `Reference`
object and pass `logger.stream_handler`. This tells Symfony: "Hey, this argument
isn't the string `logger.stream_handler`, it's a service with this id.":

[[[ code('4de0684cef') ]]]

To put this into the container, instead of calling `set`, use `setDefinition`
with the nickname `logger` and the `$loggerDefinition`. Now get rid of the
old lines that set the `logger` service directly:

[[[ code('47faee3a48') ]]]

The container now knows *how* to create the `logger` service. So later, when
and *if* we ask for it, the container will create it in the background using
these instructions.

And our app doesn't know or care this is happening - it just happily asks for
that service. So let's try it out:

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

Boom! 3 log entries now.

The disadvantage is that adding service is now more abstract: instead
of creating them directly, you describe them. But the up-side is *huge*.
Services aren't created unless you need them, the container will give you
clear error messages if you mess something up, *and* the way this is all
cached will blow your mind.

Oh, and now order doesn't matter. Down near the bottom, create a new
`Definition` and pass it the `StreamHandler` class. We can remove the `Logger`
and `StreamHandler` `use` statements too, because in a second, we won't be
referencing these directly anymore. Just like before, call `setArguments`,
pass it an array, and put the *one* constructor argument - the log file path -
inside of it. Finish it off with `$container->setDefinition()`, passing the
service nickname as the first argument, and the `Definition` next:

[[[ code('590968d674') ]]]

And now that the `logger.stream_handler` service is being set with a Definition,
we can remove the original code that set it directly.

So even though the `logger` service *needs* the stream handler service, we
can describe them in any order. When we eventually ask for the `logger`,
Symfony will go make the `logger.stream_handler` service first, then pass
it to `logger`. That's why it's called a dependency injector container: it
helps you manage dependencies.

But like before, our "app code" has no idea any of this is happening. So
when we hit the script again, there's another log message:

```bash
php dino_container/roar.php
tail dino_container/dino.log
```
