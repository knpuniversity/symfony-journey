Creating Symfony Magic
======================

If this were the Matrix, you'd be seeing 1's and 0's running across your
code. Your Symfony world is now one without rules or controls, without borders
or boundaries. Let's prove it.

We know that Symfony reads the ``_controller`` key in the request attributes
and executes that as the controller. This is set by the ``RouterListener``
but it could be set anywhere. 

Let's go adventuring!. ``UserAgentSubscriber`` listens to ``kernel.request``.
What if we just replaced the ``_controller`` key here with something else?
Let's do that, and set it to an anonymous function::

    $request->attributes->set('_controller', function(){

    });

Symfony doesn't see any difference between this function and a traditional
controller. To prove it, return a normal ``Response``::

    return new Response('Hello world!')

When we refresh, would you believe it? That actually works! Comment this line
out.

Even within listeners to a single event, there is an order of things. In
the events tab of the Profiler, you can see that our listener is the *last*
one that's called by ``kernel.request``. The ``RouterListener`` is executed
before us. That means that the routing *is* being run and the ``_controller``
key *is* being set. Later, our listener replaces it.

But even if our listener were run before the router, our little hack would
still work. That's because ``RouterListener`` has a special piece of code
near the top that checks to see if the ``_controller`` key is already set.
If it *is* set somehow, the routing actually runs. 

And since our anonymous function is a valid controller, we can still use
arguments on it just like normal. So if I go to ``/dinosaurs/22``, which
has a ``{id}`` in the route, we're allowed to have an ``$id`` argument. So
this will work just fine. All controllers are created equal.

Now, let's comment that out temporarily, or more likely permanently. 

Making an Argument Available to All Controllers
-----------------------------------------------

Here's our next challenge: pretend that it's really important to know if
the use is on a Mac or not. We're really wanting to start measuring how hipster
our userbase is. In fact, it's *so* important, that we want to be able to
have an ``$isMac`` argument in *any* controller function *anywhere* in the
system. This *won't* come from a routing wildcard like normal - we'll figure
this out by reading the ``User-Agent``.

As an example, I'm going to put ``$isMac`` into ``indexAction``. We'll pass
that into our template, and inside there, use it to print our a threatening
message if the user is on a Mac.

Back to the homepage! If we try this now, we know what's going to happen.
We get a huge error because there is no ``{isMac}`` in the route. Symfony
has no idea what to pass to the argument. And up until now that's been the
rule: whatever we have in our routing curly brace is available as an argument
and there are no exceptions to that rule, except for the Request object.


But guys, that's not true! And we know it. We nkow that it's not *really*
about the routing layer. The arguments to the controller come from the request
attributes. And sure, the only thing that normally modifies those is the
routing layer. But there's nothing stopping someone else from adding some
extra stuff.

In our subscriber, let's first get an ``$isMac`` variable. We'll look for
the ``User-Agent`` header and we'll look for the word ``mac``. Check to see
if ``strpos`` doesn't equal false.

To make this available as an argument, all we need to do is put it in the
request attributes::

    TODO

Seriously, that's it. When we refresh, it works!

And since I never trust when things work on the first try, let's change
our code to look for ``mac2``. I'm on a mac, but the message hides since
we changed that.

So why is this important? Because understanding the core of Symfony is letting
you do things that previously looked impossible. You're also going to be
able to figure out how magic from outside libraries is working.

For example look at the `SensioFrameworkExtraBundle`_. This is basically
a bundle of shortcuts that work via magic. Now that you've journied to the
center of Symfony and back, if you look at each shortcut, you should be able
to explain that magic behind each of these. 

The one I want to look at now is the ``ParamConverter``. In the example,
you can see that the controller has a ``$post`` argument, but also that there's
no ``{post}`` in the routing. So this *should* throw an error. The ``ParamConverter``,
via a listener to ``kernel.request``, grabs the ``id`` off of the request
attributes, queries for a ``Post`` object via Doctrine by that ``id``, and
then adds a new request attribute called ``post`` that's set to that object.
And just by doing that, the ``showAction`` can have that ``$post`` argument.

If that makes any sense at all, you're on the verge of *really* mastering
a big part of Symfony.

Before we talk about sub requests, I want to point something out. If you're
playing with things, inside the profiler, there is a Request tab, which is
interesting because it shows you the request ``attributes``. You can see
the ``_controller``, the routes stuff and the ``isMac`` key. By the way,
not that it's necessarily useful, but the fact that there is an ``_route``
key *does* mean that you can have a ``$_route`` argument to any controller.
