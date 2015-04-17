# The Container Dumper

After a container is built, you should `compile` it:

[[[ code('29eb04313b') ]]]

This starts one final layer to the build process, which anyone can hook into
to make final adjustments. For now, it's not doing anything - but it's really
important inside the framework.

In a big project - parsing Yaml files and collecting all this service Definition
stuff can start to take a lot of time. Our container is nice, but it's
coming at a performance cost.

Let's see how much by adding some *really* basic profiling code. Up top, add
a `$startTime` variable. And down below, figure out how much time elapsed,
multiple it by 1000 to get microseconds, and while we're here, round it.
And hey, let's use our container to get out the `logger` and debug a message
about this:

[[[ code('79bdb24b2e') ]]]

So let's see how long this takes:

```bash
php dino_container/roar.php
```

37ms at first, but then it settles to about 19ms after running a few times.
Not bad, but this is a tiny project. Just keep that 19ms number in mind.

## Caching the Container

Here's the question: can we take all of this metadata about the container
and cache it somehow? Absolutely - and the way it caches is incredible.

After compiling, create a new variable called `$dumper` and set it to a new
`PhpDumper` object. Pass the `$container` to the dumper:

[[[ code('ce66c9eeea') ]]]

This guy is an expert at taking that metadata and caching it to a file. To
do that, use the good ol' fashioned `file_put_contents` - pass it some new
file path - how about `cached_container.php` and for the contents, call
`$dumper->dump()`:

[[[ code('20237b1ef7') ]]]

Let's see what this does! Run the script again:

```bash
php dino_container/roar.php
```

Now the `cached_container.php` file pops into existence. And it's *awesome*.

## The Cached Container

Oh, so many good things to see. First, notice that this dumps a PHP class
that extends `Container`:

[[[ code('e314fe1a24') ]]]

That's actually the same base class as the `ContainerBuilder` we've been
working with, and *it* houses the all-important `get()` function that fetches
out services. In other words, this `ProjectServiceContainer` looks and acts
*just* like the `$container` we're using now.

Next, this has our two parameter values sitting on top. And if you call
`getParameter()` to fetch one, it just uses this array:

[[[ code('e72086adc8') ]]]

And now, the most *important* thing to notice: for each of our three services,
there's a concrete method that's called when we ask for that service:

[[[ code('9b8dd2a4f3') ]]]

Seriously, if you look at the `get()` function in the parent class, you'll
find that calling `$container->get('logger.std_out_logger')` will ultimately
execute this `getLogger_StdOutLoggerService()` method. 

And these methods use the *exact* PHP code we would write to instantiate
these objects directly. We pass the container Definition objects, and it
dumps the raw PHP code that those represent.

This is even more incredible when you look at the `getLoggerService()` method:

[[[ code('569c13ead3') ]]]

Look closely: it creates the new `Logger` object, passes `main` and then
passes an array, with a call to `$this->get('logger.stream_handler')`
to fetch *that* service from itself - the container. The second `arguments`
key in the Yaml file causes this.

Next, it has our two method calls: `pushHandler()` with `$this->get('logger.std_out_logger')`
and then a call to `debug()`. Everything we put into those Definitions are
dumped into a *real* PHP file that contains the raw code we would've written
anyways. 

So, if we use this container class directly, then fetching objects out of
it could not be faster. Let's do it!

## Using the Cached Container

Copy the path to the file and create a new `$cachedContainer` variable *way*
up top before we even start with the `ContainerBuilder`. Our app now has
two options: we can create the `ContainerBuilder`, load it up with the `Definition`
config and then use it, OR, if that cached container is available, we can
skip everything and just use it. After all. if we call `get('logger')` on
it, it'll give us the exact same `Logger`.

So, `if (!file_exists($cachedContainer))`, then we *do* need to do all the
building work to dump the container:

[[[ code('52e8f35803') ]]]

But one way or another, that file eventually exists. So if we require it,
we can say `$container = new \ProjectServiceContainer()`, which is the class
name used in the cache file:

[[[ code('148ee9cd99') ]]]

We're still passing this `$container` into `runApp()`, and even though it's
technically a different object, it's not going to make *any* difference.
The only thing we need to change is that `runApp()` is type-hinted with
`ContainerBuilder`. Well, it turns out that what we really need is `Container`,
which is the base class for the builder and our cached class.

So I'll change the type-hint to `Container`. And we can go a step further:
the `Container` class implements an interface called `ContainerInterface`:

[[[ code('b63fc9c81d') ]]]

Ok, try out the brand new cached container!

```bash
php dino_container/roar.php
```

It works! And woh - check out that elapsed time: **4ms**, down from 19. If
you delete the `cached_container.php` file, the next run takes 22ms because
it needs to rebuild it. Then we're right back down to 4ms. This is one reason
why Symfony is able to be so fast, even in big systems. 

Now that you've got the *real* story of how container building works, let's
see how things look inside Symfony.
