import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
export const update = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/document-meta',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
update.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
update.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
    const updateForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
        updateForm.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\RpsDocumentController::aiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
export const aiReferences = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: aiReferences.url(args, options),
    method: 'post',
})

aiReferences.definition = {
    methods: ["post"],
    url: '/rps/{rps}/document-meta/ai-references',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::aiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
aiReferences.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return aiReferences.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::aiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
aiReferences.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: aiReferences.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::aiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
    const aiReferencesForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: aiReferences.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::aiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
        aiReferencesForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: aiReferences.url(args, options),
            method: 'post',
        })
    
    aiReferences.form = aiReferencesForm
const documentMeta = {
    update: Object.assign(update, update),
aiReferences: Object.assign(aiReferences, aiReferences),
}

export default documentMeta