Making an Argument Available to All Controllers
===============================================

Here's our next challenge: pretend that it's really important to know if
the user is on a Mac or not. We're really wanting to start measuring how hipster
our user base is. In fact, it's *so* important, that we want to be able to
have an ``$isMac`` argument in *any* controller function *anywhere* in the
system. This *won't* come from a routing wildcard like normal - we'll figure
this out by reading the ``User-Agent``.

As an example, I'm going to put ``$isMac`` into ``indexAction``::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    public function indexAction($isMac)
    {
        // ...

        return $this->render('dinosaurs/index.html.twig', [
            'dinos' => $dinos,
            'isMac' => $isMac
        ]);
    }

We'll pass that into our template, and inside there, use it to print out a
threatening message if the user is on a Mac:

..  code-block:: html+jinja

    {# app/Resources/views/dinosaurs/index.html.twig #}
    {# ... #}

    {% if isMac %}
        <h3>We love eating Mac's...RAWR!</h3>
    {% endif %}

Back to the homepage! If we try this now, we know what's going to happen.
We get a huge error because there is no ``{isMac}`` in the route. Symfony
has no idea what to pass to the argument. And up until now that's been the
rule: whatever we have in our routing curly brace is available as an argument
and there are no exceptions to that rule, except for the Request object.

Allowing Other Controller Arguments
-----------------------------------

But guys, that's not true! And we know it. We know that it's not *really*
about the routing layer. The arguments to the controller come from the request
attributes. And sure, the only thing that normally modifies those is the
routing layer. But there's nothing stopping someone else from adding some
extra stuff.

In our subscriber, let's first get an ``$isMac`` variable. We'll look for
the ``User-Agent`` header and we'll look for the word ``mac``. Check to see
if ``stripos`` doesn't equal false::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $isMac = stripos($userAgent, 'Mac') !== false;
    }

To make this available as an argument, all we need to do is put it in the
request attributes::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $isMac = stripos($userAgent, 'Mac') !== false;
        $request->attributes->set('isMac', $isMac);
    }

Seriously, that's it. When we refresh, it works!

And since I never trust when things work on the first try, let's change
our code to look for ``mac2``. I'm on a mac, but the message hides since
we changed that. Go ahead and change that back

The Person Behind the Curtain (Event Listeners)
-----------------------------------------------

So why is this important? Because understanding the core of Symfony is letting
you do things that previously looked impossible. You're also going to be
able to figure out how magic from outside libraries is working.

For example look at the `SensioFrameworkExtraBundle`_. This is basically
a bundle of shortcuts that work via magic. Now that you've journeyed to the
center of Symfony and back, if you look at each shortcut, you should be able
to explain the magic behind each of these. 

The one I want to look at now is the `ParamConverter`_::

    /**
     * @Route("/blog/{id}")
     * @ParamConverter("post", class="SensioBlogBundle:Post")
     */
    public function showAction(Post $post)
    {
    }

In the example, you can see that the controller has a ``$post`` argument,
but also that there's no ``{post}`` in the routing. So this *should* throw
an error. The ``ParamConverter``, which works via a listener to ``kernel.controller``,
grabs the ``id`` off of the request attributes, queries for a ``Post`` object
via Doctrine with that ``id``, and then adds a new request attribute called ``post``
that's set to that object::

    // Summarized version of ParamConverterListener
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        $id = $request->attributes->get('id');

        $entity = $this->em->getRepository('SensioBlogBundle:Post')
            ->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('No Post found for '.$id);
        }

        $request->attributes->set('post', $entity);
    }

And just by doing that, the ``showAction`` can have that ``$post`` argument.

If that makes any sense at all, you're on the verge of *really* mastering
a big part of Symfony.

Before we talk about sub requests, I want to point something out. If you're
playing with things, inside the profiler, there is a Request tab, which is
interesting because it shows you the request ``attributes``. You can see
the ``_controller``, the routes stuff and the ``isMac`` key. By the way,
not that it's necessarily useful, but the fact that there is an ``_route``
key *does* mean that you can have a ``$_route`` argument to any controller.

.. _`SensioFrameworkExtraBundle`: http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/index.html
.. _`ParamConverter`: http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html
