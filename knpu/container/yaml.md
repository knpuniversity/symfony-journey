# Yaml for Service Definitions

Creating service definitions in PHP is just *one* way to do things: you can
configure this same stuff purely in Yaml or XML. And since Symfony uses Yaml
files, let's do that. 

Up top, create a `$loader` object - set it to `new YamlFileLoader()` from
the DependencyInjection component. This takes two arguments: the `$container`
and new `FileLocator`. This is a really simple object that says: "Hey, look
for files inside of a `config` directory". To make it read a Yaml file, call
`load()` point it at a new file called `services.yml`.

This is boilerplate code that tells the container to go find service definitions
in this file. 

Now create a `config/` directory and put that `services.yml` in there. Our
goal is to move all of this `Definition` stuff into that Yaml file. We'll
start with the `logger`. I'll comment out *all* of the `$loggerDefinition`
stuff now, but keep it as reference:

[[[ code('1a40d1cd72') ]]]

## Definitions in services.yml

The *whole* purpose of this Yaml file is to build service `Definition` objects.
So it should be no surprise that we start with a `services` key. Next, since
the nickname of the service is `logger`, put that. Indented below this is
*anything* needing to configure that `Definition` - to train the container
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
it's not adding anything there - the new ones shoudl be from 8:46.

When we say "go load services.yml", it's creating a new `Definition` for
logger whose class is `Monlog\Logger`. But since we're only passing it one
argument, we're not adding any handlers. It's going back to that default
mode where if it has no handlers, it just dumps to the screen. This means
that things *are* working, but we need to hook up those handlers.

Let's add the second argument, which points to `logger.stream_handler`. Add
another line with a dash, then paste `logger.stream_handler`. If we did *just*
this, it'll pass this in a string. In PHP code, this is where we passed in
a `Reference` object. To do the same here, we put an `@` symbol on the front.
I'll surround this in quotes - but you don't technically need to:

[[[ code('87b68caad8') ]]]

Try it! Woh, explosion! Argument 2 should be an array, but I'm passing an
object. I was sloppy. When I added the second line under `arguments`, this
is the second argument to the constructor, and I'm passing literally *one*
object. We know the second argument is an array of objects. So in Yaml, we
need to surround this with square brackets to make that an array:

[[[ code('562d313058') ]]]

If we wanted something else in that array, we'd just add a comma inside those
brackets. This time, no errors!

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

The console message is gone, but the log file gets it.

The big point is that you can create `Definition` objects by hand, OR use
a config file to do that for you. When `services.yml` is loaded, it *is*
creating those same `Definition` objects.

And as you'll see in a bit, if you want to get really advanced, you'll want
to understand both ways of creating `Definition`s.

## addMethodCall in Yaml

Next, we need to move over the `addMethodCall` stuff. In Yaml, add a `calls`
key. The bummer of the `calls` key is that it has a funny syntax. Add a new
line with a dash like `arguments`. We know that the method is called `debug`
and we need to pass that method a single string argument. In Yaml, it translates
to this. Inside square brackets pass `debug`, then another set of square
brackets for the arguments. If we wanted to pass three arguments, we'd just
put a comma-separated list. We'll just paste the message in as the only argument:

[[[ code('279fe0a6fa') ]]]

I know that's ugly. Under the hood, that's training a `Definition` so that
the `debug` method is called and it's passed this one argument. Let's go
back to the terminal and try it:

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

Tail the logs, and boom! Our extra "logger has started" message is back.
Now let's do the same for the other method call. It's exactly the same, except
the argument is a service. Copy that name, add a new line under `calls`,
say `pushHandler`, then say `@` then the paste our handler name:

[[[ code('d0fc3830d9') ]]]

This *all* sets up exactly what we had before. Try it!

```bash
php dino_container/roar.php
```

Back to dumping to the screen! And congrats! Our entire `logger` definition
is in Yaml. And this is a really complicated example - even having `calls`
is pretty rare. Celebrate by removing the commented-out `$loggerDefinition`
stuff. 
