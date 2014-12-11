How Sub-Requests Work
=====================

To learn how sub request *really* work, let's leave this behind for a second
and go back to ``DinosaurController::indexAction``. I'm going to create a
sub request right here, by hand. To do that, just create a ``Request`` object.
Next, set the ``_controller`` key on its attributes. Set it to
``AppBundle:Dinosaur:latestTweets``::

    // AppBundle/Controller/DinosaurController.php
    // ...

    public function indexAction($isMac)
    {
        // ...

        $request = new Request();
        $request->attributes->set(
            '_controller',
            'AppBundle:Dinosaur:_latestTweets'
        );

        // ...
    }

Then, I'm going to fetch the ``http_kernel`` service. Yep, that's the same
HttpKernel we've been talking about, and it lives right in the container.

Now, let's call the familiar ``handle`` function on it: the exact same ``handle``
function we've been studying. Pass it the ``$request`` object and a ``SUB_REQUEST``
constant as a second argument. I'm going to talk about that constant in a second::

    // AppBundle/Controller/DinosaurController.php
    // ...

    public function indexAction($isMac)
    {
        // ...

        $request = new Request();
        $request->attributes->set(
            '_controller',
            'AppBundle:Dinosaur:_latestTweets'
        );
        $httpKernel = $this->container->get('http_kernel');
        $response = $httpKernel->handle(
            $request,
            HttpKernelInterface::SUB_REQUEST
        );

        // ...
    }

Let's think about this. We're *already* right in the middle of an ``HttpKernel::handle()``
cycle for the main request. Now, we're starting another ``HttpKernel::handle()``
cycle. And because I'm setting the ``_controller`` attribute it knows which
controller to execute. That sub request is going to go through that whole
process and ultimately call ``_latestTweetsAction``. 

I'm not going to do anything with this ``Response`` object: I'm just trying
to prove a point. If we refresh the browser and click into the Timeline,
we now have *two* sub requests. One of them is the one I just created. If
you scroll down you can see that. The other one is coming from the ``render()``
call inside the template.

Let's comment this silliness out. I wanted to show you this because that's
*exactly* what happens inside ``base.html.twig`` when we use the ``render()``
function. It creates a brand new request object, sets the ``_controller``
key to whatever we have here, then passes it to ``HttpKernel::handle()``.

Sub-Requests have a Different Request Object
--------------------------------------------

Now we know why we're getting the weird ``isMac`` behavior in the sub request!
The ``UserAgentSubscriber`` - in fact all listeners - are called on both
requests. But the second time, the request object is **not** the *real* request.
It's just some empty-ish Request object that has the ``_controller`` set
on it. It doesn't, for example, have the same query parameters as the main
request.

That's why the first time ``UserAgentSubscriber`` runs, it reads the ``?notMac=1``
correctly. But the second time, when this is run for the sub request, there
are *no* query parameters and the override fails.

Properly Handling Sub-Request Data
----------------------------------

Here's the point: when you have a sub request, you need to *not* rely on
the information from the main request. That's because the request you're
given is **not** the real request. Internally, Symfony duplicates the main
request, so some information remains and some doesn't. That's why reading
the ``User-Agent`` header worked in the sub request. But don't rely on this:
think of the sub-request as a totally independent object.
 
So whenever you read something off of the request, you need to ask yourself...
do you feel lucky?... I mean... you need to make sure you're working with
what's called the "master" request.

This means that our ``UserAgentSubscriber`` can only do its job properly
for the master request. On a sub-request, it shouldn't do anything. So let's
add an ``if`` statement and use an ``isMasterRequest()`` method on the event
object. If this is *not* the master request, let's do nothing::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        // ...

    }

And how does Symfony know if it's handling a master or sub-requests? That's
because the second argument here. So when you call ``HttpKernel::handle()``,
we pass in a constant that says "hey this is a sub request, this is not
a master request". And then your listeners can behave differently if they
need to.

When we refresh, we get a huge error! This makes sense. ``UserAgentSubscriber``
doesn't run on the sub-request, so ``isMac`` is missing from the request
attributes. And because of that, we can no longer have an ``$isMac`` controller
argument.

Passing Information to a Sub-Request Controller
-----------------------------------------------

But wait! I *do* want to know if the user is on a Mac from my sub request.
What's the solution?

The answer is really simple: just pass it to the controller. The second argument
to the ``controller()`` function is an array of items that you want to make
available as arguments to the controller. Behind the scenes, these are put
onto the attributes of the sub request. So we can add a ``userOnMac`` key
and set its value to the *true* ``isMac`` attribute stored on the master
request. So, ``app.request.attributes.get('isMac')``:

.. code-block:: html+jinja

    {# app/Resources/views/base.html.twig #}
    {# ... #}

    {{ render(controller('AppBundle:Dinosaur:_latestTweets', {
        'userOnMac': app.request.attributes.get('isMac')
    })) }}

Inside of the controller, add a ``userOnMac`` variable and pass it into the
template::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    public function _latestTweetsAction($userOnMac)
    {
        // ...

        return $this->render('dinosaurs/_latestTweets.html.twig', [
            'tweets' => $tweets,
            'isMac' => $userOnMac
        ]);
    }

Now when we refresh, we still have the ``?notMac=1``, so the Mac message
is missing from the master request part at the top. And if we scroll down,
the sub request *also* knows we're not on a mac because we're passing that
information through.

When we take off the query parameter, it looks like we're on a mac up top
on the bottom. Brilliant!

The lesson is that you need to be careful *not* to read outside request information,
like query parameters from the URL, from inside a sub-request. This also
ties into Http caching and ESI which are topics we'll cover later. If we
follow this rule and you *do* want to cache this latest tweets fragment,
it's going to be super easy.

Seeya next time!
