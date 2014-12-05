kernel.controller Event & Controller Arguments
==============================================

So what next? Well, let's dispatch another event. This time it's called ``kernel.controller``.
And you can see that it's passed a different event object called FilterControllerEvent.
What's unique about *this* event is that you have access to what the controller
is. So if you needed to do something in your application based on what the
controller is, or event *change* what the controller is, then you need a
listener on this event.

As far as understanding the Symfony Framework, there's no mission-critical
listeners on this event, so I'm not going to show any. 

So can we finally call the controller? Nope! Because we need to figure out
what arguments to pass to that function. To figure this out, we use our
ControllerResolver again by calling ``getArguments``.

Open HttpKernel's ControllerResolver back up and scroll down to find this
function. Remember, the controller is *any* callable, which could be one
of several things. This entire if statement is just a way to get information -
using PHP Reflection - about the arguments to this controller callable.

Ultimately, *those* are what's passed as the ``$parameters`` argument to
``doGetArguments``. For example, let me show you what I mean. Let's dump
``$parameters`` and put die. If we look at DinosaurController::showAction,
we have one argument, which is ``$id``. The route has ``{id}``, so this is
``$id`` - we all know how those match up to each other. In fact, we're about
to see how that all works. So if I go to ``/dinosaurs/22``, you can see that
the ``$parameters`` that are dumped have just one item in it, and in that
``ReflectionParameter`` is a name with ``id``, and that ``id`` is coming
from the ``$id`` argument.

So if we add a few more arguments and refresh, we'll have *three* items in
``$paramters``. This one has ``foo`` and this one has ``bar``. So it's just
metadata about what arguments are on that controller function.

In ``doGetArguments``, the first thing it does is *so* important and *so*
awesome. It goes *back* to the ``$request->attributes`` - that same old thing
that was populated by the routing. As a reminder, let's dump this out quickly.
When we refresh, it has ``_controller``, it also has the ``id`` from the
routing wildcard and it has a couple of other things that honestly aren't
very important.

So keep that in mind. The function iterates over all of the ``$parameters``,
all of the arguments to our controller function. And the first thing it does
is check to see if the name of the parameter - like ``id`` - exists in the
``$attributes`` array. And if it does, it uses that value as an argument.
This is the *key* behind the scenes to why we can have a ``{id}`` inside
our route - whose value is stored in the request attributes - and have that
mapped to an ``$id`` argument of our controller function. This is why and
how the mapping is done by *name*, because ControllerResolver say: "Hey,
I have an ``$id`` argument, is there an ``id`` inside the ``$request->attributes``,
which is populated by the routing."

And *if* for some reason there is nothing that matches up, it goes to the
``elseif`` - because there's one other case that works. And you've probably
seen it while doing form processing. This is when you have a ``Request``
argument. In ControllerResolver, it says:
``if ($param->getClass() && $param->getClass()->isInstance($request))``.
The ``$param->getClass()`` asks if the argument has a type-hint. And so this
is checking to see if the argument is type-hinted with Symfony's Request
class. And if so, let's pass the ``$request`` object to this argument. This
is *completely* special to the Request object - it doesn't work with anything
else. Our whole job is to read the request and create the response. And it
kind of makes sense that receiving the request as an argument to your controller
would be useful.

And those are really the only two cases that work. The other ``elseif`` is
there just to see if maybe you have an argument, which is optional, because
we gave it a default value. So it'll just use that value instead, it won't
blow up because it's missing.

If all else fails, it just tries to get a nice exception message to say:
Hey dude, you have an argument and I don't know what to pass to it. For example,
if I add a ``$bar`` argument that's not optional, there's no ``{bar}`` in
the route, so we should see this "Controller requires that you provide a value"
error. And we do! That's actually where that comes from.

If we get rid of that, it should pass the request, it should be ok with
``$foo`` being optional. When we refresh, it's happy!

Ultimately, in ControllerResolver, it returns an array of values to pass to
each of the arguments of our controller function. This is really cool because
it means back in ``HttpKernel``, we now have a controller function *and* the
arguments to pass to it.

Hmm, so what should we do? We *finally* call the controller. So on line 145,
that's literally where your controller is called, and it passes you all of
the arguments.

And what do controllers in Symfony *always* return? They always return a
Response object, and we see that on line 145.

Unless... they don't return a Response. And that's what we're going to talk
about next.
