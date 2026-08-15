import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
export const update = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/weeks/{week}/weight',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
update.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
update.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::update
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
    const updateForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
        updateForm.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const weight = {
    update: Object.assign(update, update),
}

export default weight