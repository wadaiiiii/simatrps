import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/rps',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::index
 * @see app/Http/Controllers/RpsController.php:18
 * @route '/rps'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/rps/baru',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::create
 * @see app/Http/Controllers/RpsController.php:40
 * @route '/rps/baru'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsController::store
 * @see app/Http/Controllers/RpsController.php:89
 * @route '/rps'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
export const show = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/rps/{rps}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return show.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.get = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
show.head = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
    const showForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
        showForm.get = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RpsController::show
 * @see app/Http/Controllers/RpsController.php:111
 * @route '/rps/{rps}'
 */
        showForm.head = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const RpsController = { index, create, store, show }

export default RpsController