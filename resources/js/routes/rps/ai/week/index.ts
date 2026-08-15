import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:185
 * @route '/rps/{rps}/ai/weeks/{week}'
 */
export const generate = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

generate.definition = {
    methods: ["post"],
    url: '/rps/{rps}/ai/weeks/{week}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:185
 * @route '/rps/{rps}/ai/weeks/{week}'
 */
generate.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
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

    return generate.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:185
 * @route '/rps/{rps}/ai/weeks/{week}'
 */
generate.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:185
 * @route '/rps/{rps}/ai/weeks/{week}'
 */
    const generateForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:185
 * @route '/rps/{rps}/ai/weeks/{week}'
 */
        generateForm.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generate.url(args, options),
            method: 'post',
        })
    
    generate.form = generateForm
const week = {
    generate: Object.assign(generate, generate),
}

export default week