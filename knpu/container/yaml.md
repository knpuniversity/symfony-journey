# Yaml for Service Definitions

Creating service definitions in PHP is just *one* way to do things: you can
configure this same stuff purely in Yaml or XML. And since Symfony uses Yaml
files, let's use them too.

Up top, create a `$loader` object - set it to `new YamlFileLoader()` from
the DependencyInjection component. This takes two arguments: the `$container`
and a new `FileLocator`. That's a really simple object that says: "Yo, look
for files inside of a `config` directory". To make it read a Yaml file, call
`load()` and point it at a new file called `services.yml`.

[[[ code('8fbb0e03c0') ]]]

This code tells the container to go find service definitions in this file. 

Now create a `config/` directory and put that `services.yml` in there. Our
goal is to move all of this `Definition` stuff into that Yaml file. We'll
start with the `logger`. I'll comment out *all* of the `$loggerDefinition`
stuff, but keep it as reference:

[[[ code('1a40d1cd72') ]]]

## Definitions in services.yml

The *whole* purpose of this Yaml file is to build service `Definition` objects.
So it should be no surprise why we start with a `services` key. Next, since
the nickname of the service is `logger`, put that. Indented below this is
*anything* needing to configure this `Definition` object: to train the container
on how to create the `logger` service.

Almost every service will at least have two parts: the `class` - set it to
`Monolog\Logger` and `arguments`. We know we have 2 arguments. The first is
the string `main` and the second is an array with a reference to another
service. To add the first, just say `main`:

[[[ code('768089b63c') ]]]

Before we put the second argument, let's just make sure things are *not*
exploding so far:

```bash
php dino_container/roar.php
```

No explosion! It's printing out to the screen, but if you look in the log,
it's not adding anything there - the new ones should be from 8:46.

When we say "go load services.yml", it creates a new `Definition` object
for `logger`. But that logger doesn't have any handlers yet, so it's reverting
to that default mode where it just dumps to the screen.

To hook up the first handler, our constructor needs a second argument. Add
another line with a dash, then paste `logger.stream_handler`. If we did *just*
this, it'll pass this in as a string. In PHP code, this is where we passed in
a `Reference` object. To make this create a Reference, we put an `@` symbol
in front. I'll surround this in quotes - but you don't technically need to:

[[[ code('87b68caad8') ]]]

Try it! Woh, explosion! Argument 2 should be an array, but I'm passing an
object. I was sloppy. For my second argument, I'm passing literally *one*
object. But we know the second argument is an array of objects. So in Yaml,
we need to surround this with square brackets to make that an array:

[[[ code('562d313058') ]]]

This time, no errors!

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

The console message is gone, but the log file gets it.

The big point is that you can create `Definition` objects by hand, OR use
a config file to do that for you. When `services.yml` is loaded, it *is*
creating those same `Definition` objects.

And as you'll see in a bit, if you want to get really advanced, you'll want
to understand both ways.

## addMethodCall in Yaml

Next, we need to move over the `addMethodCall` stuff. In Yaml, add a `calls`
key. The bummer of the `calls` key is that it has a funny syntax. Add a new
line with a dash like `arguments`. We know that the method is called `debug`
and we need to pass that method a single string argument. In Yaml, it translates
to this. Inside square brackets pass `debug`, then another set of square
brackets for the arguments. If we wanted to pass three arguments, we'd just
put a comma-separated list. We'll just paste the message in as the only argument:

[[[ code('279fe0a6fa') ]]]

I know that's ugly. But under the hood, that's just calling `addMethodCall`
on the `Definition` and passing it `debug` and this arguments array. Let's go
back to the terminal and try it:

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

Tail the logs, and boom! Our extra "logger has started" message is back.
Now let's do the same for the other method call. It's exactly the same, except
the argument is a service. Copy that name, add a new line under `calls`,
say `pushHandler`, `@` then the paste our handler name:

[[[ code('d0fc3830d9') ]]]

Test it out.

```bash
php dino_container/roar.php
```

Yes! Both handlers are back! And congrats! Our entire `logger` definition
is now in Yaml. And this is a pretty complicated example - most services
are just a class and arguments. Celebrate by removing the commented-out
`$loggerDefinition` stuff. 
