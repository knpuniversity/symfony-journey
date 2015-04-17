# Parameters

Let's finish this up by converting both handlers to Yaml. Do the "stdout"
logger first - it's easier. Under the `services` key, add a new entry
for `logger.std_out_logger` and give it the class name:

[[[ code('fe519b765a') ]]]

Peak back - this has one argument. So add the `arguments` key and give it
the `php://stdout`. Those quotes are optional, and if you want, you can put
the arguments up onto one line, inside square brackets:

[[[ code('08c5ef7b32') ]]]

And as long as this still prints to the screen, life is good:

```bash
php dino_container/roar.php
```

Perfect!

## Adding a Parameter in PHP

Now let's move the *other* handler. But this one is a little trickier: its
argument has a PHP expression - `__DIR__`. That's trouble.

But hey, ignore it for now! Copy the service name and put it into `services.yml`.
The order of services does *not* matter. Pass it the class and give it a
single argument. This will *not* work, but I'll copy the `__DIR__.'/dino.log`
in as the argument:

That's the basic idea, but since that `__DIR__` stuff is PHP code, this won't
work. But the solution is really nice.

The container holds *more* than services. It *also* has a simple key-value
configuration system called parameters. In PHP, to add a parameter, just say
`$container->setParameter()` and invent a name. How about `root_dir`? And
we'll set its value to `__DIR__`:

[[[ code('ec40b1cf71') ]]]

That doesn't *do* anything, but now we can use that `root_dir` parameter
*anywhere* else when we're building the container.

To use a parameter in Yaml, say `%root_dir%`:

[[[ code('46d6cb4a15') ]]]

With everything in Yaml, we can clean up! We don't need any `Definition`
code at all in `roar.php` - just create the container, set the parameter
and load the yaml file:

[[[ code('6d5b774a93') ]]]

Ok, moment of truth!

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

It still prints! And it's still adding to our log file. And now all that service
Definition code is sitting in `services.yml`.

## Parameters in Yaml

Of course, you can also add parameters in Yaml. Add a `parameters` root key
somewhere - order doesn't matter - and invent one called `logger_start_message`.
Copy the string from the `debug` call and paste it. Now that we have a second
parameter, we can grab the key and use it inside two percents:

[[[ code('37ca9e6682') ]]]

And this still works just like before.

This last point is actually really important. Yaml files that build the container
only have *three* valid root keys: `services`, `parameters` and another called
`imports`, which just loads other files. And that makes sense. After all,
a container is nothing more than a collection of services and parameters.
This point will be really important later. Because in Symfony, files like
`config.yml` violate this rule with root keys like `framework` and `twig`.

With all this hard work behind us, we're about to see one of the coolest
features of the container, and the reason why it's so fast.
